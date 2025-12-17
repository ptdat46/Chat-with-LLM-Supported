<?php

namespace App\Console\Commands;

use App\Models\Message;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ExportMessagesForTraining extends Command
{
    protected $signature = 'messages:export-training 
                            {--output=train.jsonl : Output file name}
                            {--python : Use Python Scrubadub for PII redaction}';
    
    protected $description = 'Export messages with PII redaction for model training';

    public function handle()
    {
        $outputFile = $this->option('output');
        $usePython = $this->option('python');

        $this->info('Fetching messages from database...');
        $messages = Message::with('user')->get();
        
        if ($messages->isEmpty()) {
            $this->error('No messages found in database!');
            return 1;
        }

        $this->info("Found {$messages->count()} messages");

        if ($usePython) {
            return $this->exportWithPython($messages, $outputFile);
        }

        return $this->exportWithPhp($messages, $outputFile);
    }

    private function exportWithPhp($messages, $outputFile)
    {
        $this->info('Using PHP for PII redaction...');
        $bar = $this->output->createProgressBar($messages->count());
        $bar->start();

        $jsonlContent = '';
        
        foreach ($messages as $message) {
            $cleanedText = $this->redactPII($message->text);
            
            $trainingData = [
                'text' => $cleanedText,
                'user' => $message->user ? $message->user->name : 'Unknown',
                'created_at' => $message->created_at->toIso8601String(),
            ];
            
            $jsonlContent .= json_encode($trainingData, JSON_UNESCAPED_UNICODE) . "\n";
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        // Save to storage
        Storage::disk('local')->put($outputFile, $jsonlContent);
        $fullPath = storage_path("app/{$outputFile}");
        
        $this->newLine();
        $this->info("✓ Exported {$messages->count()} messages to: {$fullPath}");
        $this->info("File size: " . $this->formatBytes(strlen($jsonlContent)));
        
        return 0;
    }

    private function exportWithPython($messages, $outputFile)
    {
        $this->info('Checking Python installation...');
        
        // Try to find Python executable
        $pythonCmd = $this->findPythonExecutable();
        
        if (!$pythonCmd) {
            $this->warn('Python not found in PATH!');
            $this->warn('Falling back to PHP PII redaction...');
            $this->newLine();
            return $this->exportWithPhp($messages, $outputFile);
        }
        
        $this->info("Found Python: {$pythonCmd}");
        $this->info('Using Python Scrubadub for PII redaction...');
        
        // Export raw data to temp file
        $tempFile = 'temp_messages.json';
        $rawData = $messages->map(function ($message) {
            return [
                'id' => $message->id,
                'text' => $message->text,
                'user' => $message->user ? $message->user->name : 'Unknown',
                'created_at' => $message->created_at->toIso8601String(),
            ];
        })->toArray();
        
        Storage::disk('local')->put($tempFile, json_encode($rawData, JSON_UNESCAPED_UNICODE));
        $tempPath = storage_path("app/{$tempFile}");
        $outputPath = storage_path("app/{$outputFile}");

        // Create Python script if not exists
        $this->createPythonScript();
        
        // Run Python script
        $pythonScript = storage_path('app/redact_pii.py');
        $command = "\"{$pythonCmd}\" \"{$pythonScript}\" \"{$tempPath}\" \"{$outputPath}\"";
        
        $this->info('Running Python script...');
        
        exec($command . ' 2>&1', $output, $returnCode);
        
        if ($returnCode !== 0) {
            $this->error('Python script failed!');
            $this->line(implode("\n", $output));
            
            // Check if scrubadub is missing
            if (stripos(implode(' ', $output), 'scrubadub') !== false || 
                stripos(implode(' ', $output), 'ModuleNotFoundError') !== false) {
                $this->warn('');
                $this->warn('Scrubadub not installed! Install it with:');
                $this->warn('  pip install scrubadub');
                $this->warn('');
                $this->warn('Or use PHP mode (without --python flag)');
            }
            
            // Clean up temp file
            Storage::disk('local')->delete($tempFile);
            return 1;
        }

        // Clean up temp file
        Storage::disk('local')->delete($tempFile);
        
        $this->newLine();
        $this->info("✓ Exported with Python Scrubadub to: {$outputPath}");
        
        return 0;
    }

    private function findPythonExecutable()
    {
        // Try common Python commands on Windows
        $pythonCommands = ['python', 'python3', 'py'];
        
        foreach ($pythonCommands as $cmd) {
            // Test if command exists and works
            exec("{$cmd} --version 2>&1", $output, $returnCode);
            if ($returnCode === 0) {
                return $cmd;
            }
        }
        
        // Try to find Python in common installation paths
        $commonPaths = [
            'C:\Python39\python.exe',
            'C:\Python310\python.exe',
            'C:\Python311\python.exe',
            'C:\Python312\python.exe',
            'C:\Program Files\Python39\python.exe',
            'C:\Program Files\Python310\python.exe',
            'C:\Program Files\Python311\python.exe',
            'C:\Program Files\Python312\python.exe',
            getenv('LOCALAPPDATA') . '\Programs\Python\Python39\python.exe',
            getenv('LOCALAPPDATA') . '\Programs\Python\Python310\python.exe',
            getenv('LOCALAPPDATA') . '\Programs\Python\Python311\python.exe',
            getenv('LOCALAPPDATA') . '\Programs\Python\Python312\python.exe',
        ];
        
        foreach ($commonPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        
        return null;
    }

    private function redactPII(string $text): string
    {
        // Redact Vietnamese phone numbers
        $patterns = [
            // Phone numbers (0xxx xxx xxx, +84 xxx xxx xxx, etc.)
            '/(\+84|0)?[1-9][0-9]{8,9}\b/' => '[PHONE]',
            
            // Email addresses
            '/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/' => '[EMAIL]',
            
            // Credit card numbers (16 digits with optional spaces/dashes)
            '/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/' => '[CARD]',
            
            // ID numbers (CCCD, CMND - 9 or 12 digits)
            '/\b\d{9}\b|\b\d{12}\b/' => '[ID]',
            
            // URLs
            '/https?:\/\/[^\s]+/' => '[URL]',
            
            // Vietnamese names with titles (Anh, Chị, Em, etc. + Name)
            '/(Anh|Chị|Em|Mr\.|Mrs\.|Ms\.)\s+[A-ZĐÀÁẠẢÃÂẦẤẬẨẪĂẰẮẶẲẴÈÉẸẺẼÊỀẾỆỂỄÌÍỊỈĨÒÓỌỎÕÔỒỐỘỔỖƠỜỚỢỞỠÙÚỤỦŨƯỪỨỰỬỮỲÝỴỶỸ][a-zđàáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹ]+(\s+[A-ZĐÀÁẠẢÃÂẦẤẬẨẪĂẰẮẶẲẴÈÉẸẺẼÊỀẾỆỂỄÌÍỊỈĨÒÓỌỎÕÔỒỐỘỔỖƠỜỚỢỞỠÙÚỤỦŨƯỪỨỰỬỮỲÝỴỶỸ][a-zđàáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹ]+)*\b/' => '[NAME]',
            
            // Capitalized names (2-3 words starting with capital)
            '/\b[A-ZĐÀÁẠẢÃÂẦẤẬẨẪĂẰẮẶẲẴÈÉẸẺẼÊỀẾỆỂỄÌÍỊỈĨÒÓỌỎÕÔỒỐỘỔỖƠỜỚỢỞỠÙÚỤỦŨƯỪỨỰỬỮỲÝỴỶỸ][a-zđàáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹ]{2,}\s+[A-ZĐÀÁẠẢÃÂẦẤẬẨẪĂẰẮẶẲẴÈÉẸẺẼÊỀẾỆỂỄÌÍỊỈĨÒÓỌỎÕÔỒỐỘỔỖƠỜỚỢỞỠÙÚỤỦŨƯỪỨỰỬỮỲÝỴỶỸ][a-zđàáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹ]{2,}(\s+[A-ZĐÀÁẠẢÃÂẦẤẬẨẪĂẰẮẶẲẴÈÉẸẺẼÊỀẾỆỂỄÌÍỊỈĨÒÓỌỎÕÔỒỐỘỔỖƠỜỚỢỞỠÙÚỤỦŨƯỪỨỰỬỮỲÝỴỶỸ][a-zđàáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹ]{2,})?\b/' => '[NAME]',
            
            // Addresses with street numbers
            '/\d+\s+[A-ZĐÀÁẠẢÃÂẦẤẬẨẪĂẰẮẶẲẴÈÉẸẺẼÊỀẾỆỂỄÌÍỊỈĨÒÓỌỎÕÔỒỐỘỔỖƠỜỚỢỞỠÙÚỤỦŨƯỪỨỰỬỮỲÝỴỶỸ][a-zđàáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹ]+(\s+[A-ZĐÀÁẠẢÃÂẦẤẬẨẪĂẰẮẶẲẴÈÉẸẺẼÊỀẾỆỂỄÌÍỊỈĨÒÓỌỎÕÔỒỐỘỔỖƠỜỚỢỞỠÙÚỤỦŨƯỪỨỰỬỮỲÝỴỶỸ][a-zđàáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹ]+){2,}/' => '[ADDRESS]',
        ];

        $cleanedText = $text;
        foreach ($patterns as $pattern => $replacement) {
            $cleanedText = preg_replace($pattern, $replacement, $cleanedText);
        }

        return $cleanedText;
    }

    private function createPythonScript()
    {
        $scriptPath = storage_path('app/redact_pii.py');
        
        if (file_exists($scriptPath)) {
            return;
        }

        $pythonCode = <<<'PYTHON'
# -*- coding: utf-8 -*-
import json
import sys
import os

# Fix Windows console encoding
if sys.platform == 'win32':
    import codecs
    sys.stdout = codecs.getwriter('utf-8')(sys.stdout.buffer, 'strict')
    sys.stderr = codecs.getwriter('utf-8')(sys.stderr.buffer, 'strict')

try:
    import scrubadub
except ImportError:
    print("ERROR: scrubadub is not installed!")
    print("Install it with: pip install scrubadub")
    sys.exit(1)

def redact_pii(text):
    """Redact PII using Scrubadub"""
    try:
        scrubber = scrubadub.Scrubber()
        return scrubber.clean(text)
    except Exception as e:
        print(f"Warning: Failed to redact text: {str(e)}")
        return text

def main():
    if len(sys.argv) != 3:
        print("Usage: python redact_pii.py <input_json> <output_jsonl>")
        sys.exit(1)
    
    input_file = sys.argv[1]
    output_file = sys.argv[2]
    
    # Read input JSON
    try:
        with open(input_file, 'r', encoding='utf-8') as f:
            messages = json.load(f)
    except Exception as e:
        print(f"ERROR: Failed to read input file: {str(e)}")
        sys.exit(1)
    
    print(f"Processing {len(messages)} messages...")
    
    # Process and write JSONL
    try:
        with open(output_file, 'w', encoding='utf-8') as f:
            for i, message in enumerate(messages):
                cleaned_text = redact_pii(message['text'])
                
                training_data = {
                    'text': cleaned_text,
                    'user': message['user'],
                    'created_at': message['created_at']
                }
                
                f.write(json.dumps(training_data, ensure_ascii=False) + '\n')
                
                if (i + 1) % 100 == 0:
                    print(f"Processed {i + 1}/{len(messages)} messages")
        
        print(f"SUCCESS: Exported {len(messages)} messages to {output_file}")
        
    except Exception as e:
        print(f"ERROR: Failed to write output file: {str(e)}")
        sys.exit(1)

if __name__ == "__main__":
    main()
PYTHON;

        file_put_contents($scriptPath, $pythonCode);
        $this->info("Created Python script at: {$scriptPath}");
    }

    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}
