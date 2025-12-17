<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GenerateManyMessages extends Command
{
    protected $signature = 'messages:generate {count=1000} {--model=gemma3:4b}';
    protected $description = 'Generate many messages with diverse topics using Ollama';

    private $topics = [
        // Công nghệ & Công việc
        'Setup và cấu hình Laravel Reverb cho real-time chat',
        'Tối ưu performance React components',
        'Review code và đề xuất cải tiến',
        'Báo cáo tiến độ dự án với sếp',
        'Thảo luận về deadline và kế hoạch sprint',
        
        // Đời sống hằng ngày
        'Chia sẻ về món ăn ngon cuối tuần',
        'Thảo luận về phim hay xem gần đây',
        'Kế hoạch đi du lịch dịp lễ',
        'Chia sẻ về sở thích và hobbies',
        'Bàn về thời tiết và kế hoạch hôm nay',
        
        // Bạn bè
        'Hẹn gặp nhau cafe cuối tuần',
        'Tâm sự về công việc và cuộc sống',
        'Nhờ bạn tư vấn mua đồ điện tử',
        'Chia sẻ về trải nghiệm mua sắm',
        'Bàn về game và giải trí',
        
        // Gia đình
        'Hỏi thăm sức khỏe bố mẹ',
        'Bàn về việc tổ chức sinh nhật trong gia đình',
        'Chia sẻ về con cái và nuôi dạy trẻ',
        'Thảo luận về việc sửa chữa nhà cửa',
        'Kế hoạch về quê thăm ông bà',
        
        // Người yêu
        'Nhắn nhủ và hỏi thăm người yêu',
        'Bàn về kế hoạch hẹn hò cuối tuần',
        'Chia sẻ cảm xúc và suy nghĩ',
        'Thảo luận về tương lai của hai người',
        'Nhắn tin ngọt ngào và lãng mạn',
        
        // Nhân viên - Sếp
        'Xin nghỉ phép và giải trình lý do',
        'Báo cáo kết quả công việc với quản lý',
        'Xin ý kiến sếp về hướng giải quyết vấn đề',
        'Thảo luận về tăng lương và thăng tiến',
        'Xin feedback về performance',
        
        // Đồng nghiệp
        'Bàn về áp lực công việc',
        'Chia sẻ tips làm việc hiệu quả',
        'Góp ý về cách xử lý task',
        'Khen ngợi và động viên đồng nghiệp',
        'Chia sẻ về văn hóa công ty',
        
        // Sức khỏe & Thể thao
        'Chia sẻ về thói quen tập gym',
        'Bàn về chế độ ăn uống lành mạnh',
        'Rủ nhau đi chạy bộ buổi sáng',
        'Thảo luận về kết quả bóng đá',
        'Chia sẻ về yoga và thiền',
        
        // Mua sắm & Tài chính
        'Tư vấn mua laptop và điện thoại',
        'Chia sẻ về deal giảm giá',
        'Bàn về đầu tư và tiết kiệm',
        'Thảo luận về mua nhà mua xe',
        'Góp ý về quản lý chi tiêu',
        
        // Giáo dục & Học tập
        'Chia sẻ về khóa học online',
        'Bàn về kế hoạch học thêm kỹ năng',
        'Tư vấn chọn trường cho con',
        'Thảo luận về đọc sách',
        'Góp ý về cách học hiệu quả',
        
        // Giải trí & Văn hóa
        'Bàn về concert và sự kiện âm nhạc',
        'Chia sẻ về triển lãm nghệ thuật',
        'Thảo luận về series Netflix mới',
        'Review nhà hàng và quán café',
        'Bàn về trending trên mạng xã hội',
    ];

    private $conversationHistory = [];
    private $currentTopic = '';
    private $existingMessages = [];

    public function handle()
    {
        $totalCount = $this->argument('count');
        $model = $this->option('model');
        $host = 'http://localhost:11434';

        $users = User::all();
        if ($users->isEmpty()) {
            $this->error('No users found. Please run: php artisan db:seed');
            return 1;
        }

        // Load tất cả messages đã tồn tại
        $this->existingMessages = Message::pluck('text')->toArray();
        $this->info("Found " . count($this->existingMessages) . " existing messages in database");

        $this->info("Generating {$totalCount} messages using {$model}");
        $bar = $this->output->createProgressBar($totalCount);
        $bar->start();

        $generated = 0;
        $messagesPerTopic = rand(20, 30);
        $topicIndex = 0;
        $retryCount = 0;
        $maxRetry = 5;

        while ($generated < $totalCount) {
            // Đổi chủ đề sau mỗi 20-30 messages
            if ($generated % $messagesPerTopic === 0) {
                $this->currentTopic = $this->topics[$topicIndex % count($this->topics)];
                $this->conversationHistory = [];
                $topicIndex++;
                $messagesPerTopic = rand(20, 30);
            }

            try {
                $user = $users->random();
                $context = $this->buildContext();
                $prompt = $this->createPrompt($user->name, $context);

                $response = Http::timeout(30)->post("{$host}/api/generate", [
                    'model' => $model,
                    'prompt' => $prompt,
                    'stream' => false,
                    'options' => [
                        'temperature' => 0.8,
                        'top_p' => 0.9,
                    ]
                ]);

                if ($response->successful()) {
                    $text = $this->cleanMessage($response->json('response'));
                    
                    // Kiểm tra message không rỗng, không quá dài, và không trùng
                    if (!empty($text) && strlen($text) <= 500 && !$this->isDuplicate($text)) {
                        Message::create([
                            'user_id' => $user->id,
                            'text' => $text,
                        ]);

                        // Thêm vào existing messages để tránh trùng trong lần chạy tiếp theo
                        $this->existingMessages[] = $text;

                        $this->conversationHistory[] = ['user' => $user->name, 'text' => $text];
                        if (count($this->conversationHistory) > 5) {
                            array_shift($this->conversationHistory);
                        }

                        $generated++;
                        $bar->advance();
                        $retryCount = 0;
                    } else {
                        $retryCount++;
                        if ($retryCount >= $maxRetry) {
                            $this->warn("\nSkipped duplicate/invalid after {$maxRetry} retries");
                            $retryCount = 0;
                        }
                    }
                }

                usleep(100000); // 0.1s delay
            } catch (\Exception $e) {
                $this->warn("\nError: " . $e->getMessage());
            }
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✓ Generated {$generated} messages successfully!");
        return 0;
    }

    private function buildContext(): string
    {
        if (empty($this->conversationHistory)) {
            return "Bắt đầu thảo luận về: {$this->currentTopic}";
        }

        $context = "Đang thảo luận về: {$this->currentTopic}\nTin nhắn gần đây:\n";
        foreach ($this->conversationHistory as $msg) {
            $context .= "- {$msg['user']}: {$msg['text']}\n";
        }
        return $context;
    }

    private function createPrompt(string $userName, string $context): string
    {
        return "Bạn là {$userName}, lập trình viên đang chat với team.\n\n" .
               "{$context}\n\n" .
               "Viết 1 tin nhắn ngắn (1-2 câu) tiếp nối cuộc trò chuyện. " .
               "Tin nhắn phải tự nhiên, liên quan đến ngữ cảnh. " .
               "CHỈ TRẢ VỀ NỘI DUNG TIN NHẮN.";
    }

    private function cleanMessage(string $message): string
    {
        $message = trim($message, '"\'');
        $message = preg_replace('/^[A-Za-z\s]+:\s*/', '', $message);
        $message = preg_replace('/^(Tin nhắn|Message|Response|Trả lời):\s*/i', '', $message);
        return trim($message);
    }

    private function isDuplicate(string $text): bool
    {
        // So sánh với messages đã tồn tại
        foreach ($this->existingMessages as $existingText) {
            // So sánh tuyệt đối
            if (strtolower(trim($text)) === strtolower(trim($existingText))) {
                return true;
            }
            // So sánh độ tương tự (nếu giống > 85%)
            similar_text(strtolower($text), strtolower($existingText), $percent);
            if ($percent > 85) {
                return true;
            }
        }
        return false;
    }
}
