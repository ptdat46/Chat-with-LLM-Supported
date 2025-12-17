<?php

namespace Database\Seeders;

use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Model;

class MessageSeeder extends Seeder
{
    /**
     * Seed the messages table with conversational data.
     */
    public function run(): void
    {
        $users = User::all()->keyBy('email');

        $dialogue = [
            // Sprint planning & setup
            ['email' => 'anh.nguyen@example.com', 'text' => 'Chào team, hôm nay ta hoàn thiện giao diện chat và kiểm tra realtime.'],
            ['email' => 'binh.tran@example.com', 'text' => 'Ok, mình giữ Reverb trên 8081, queue:listen đã bật.'],
            ['email' => 'chi.pham@example.com', 'text' => 'Mình seed thêm dữ liệu để QA test scroll, phân trang và tìm kiếm.'],
            ['email' => 'anh.nguyen@example.com', 'text' => 'Nhớ chỉnh APP_URL khớp 127.0.0.1:8000, tránh lỗi connection refused.'],
            ['email' => 'binh.tran@example.com', 'text' => 'Đã clear config cache, serve đang chạy, reverb cũng ok.'],
            ['email' => 'chi.pham@example.com', 'text' => 'Checklist 4 terminal: serve, reverb:start, queue:listen, npm run dev.'],

            // Bug fixing
            ['email' => 'anh.nguyen@example.com', 'text' => 'Mình thấy đôi khi WebSocket reconnect chậm khi laptop sleep, ai gặp chưa?'],
            ['email' => 'binh.tran@example.com', 'text' => 'Mình thử offline/online, Echo tự join lại channel_for_everyone ổn.'],
            ['email' => 'chi.pham@example.com', 'text' => 'Nếu vẫn delay, thử reload tab. Có thể do cache JS cũ.'],

            // Pagination & performance
            ['email' => 'anh.nguyen@example.com', 'text' => 'Phân trang: load 50 tin mới nhất, khi kéo lên sẽ fetch tiếp.'],
            ['email' => 'binh.tran@example.com', 'text' => 'Mình sẽ log thời gian render để xem có drop frame không.'],
            ['email' => 'chi.pham@example.com', 'text' => 'QA yêu cầu đủ 40+ tin để test thanh cuộn và auto-scroll xuống cuối.'],

            // Dev experience
            ['email' => 'anh.nguyen@example.com', 'text' => 'Sau khi đổi env, luôn chạy php artisan config:clear và cache:clear.'],
            ['email' => 'binh.tran@example.com', 'text' => 'Nếu quên REVERB_SERVER_PORT, server sẽ bind 8080 và lỗi ngay.'],
            ['email' => 'chi.pham@example.com', 'text' => 'Mình thêm ghi chú đó vào README phần Troubleshooting.'],

            // Feature ideas
            ['email' => 'anh.nguyen@example.com', 'text' => 'Muốn demo typing indicator với Echo presence, để hôm sau làm.'],
            ['email' => 'binh.tran@example.com', 'text' => 'Typing giả trong seeder không cần, giữ hội thoại thật nhất.'],
            ['email' => 'chi.pham@example.com', 'text' => 'Mình sẽ thêm vài đoạn hội thoại dài hơn, có chuyển chủ đề.'],

            // Context switching & UX
            ['email' => 'anh.nguyen@example.com', 'text' => 'Mobile viewport cuộn khá mượt, nhưng nút gửi hơi sát mép, mình sẽ thêm padding.'],
            ['email' => 'binh.tran@example.com', 'text' => 'Đang theo dõi queue, chưa thấy backlog. Thời gian xử lý < 100ms.'],
            ['email' => 'chi.pham@example.com', 'text' => 'Thêm trạng thái đang gửi để người dùng biết request chưa hoàn tất.'],

            // Deployment & ops
            ['email' => 'anh.nguyen@example.com', 'text' => 'Checklist deploy: env, migrate, npm run build, reverb:start, queue:listen, serve.'],
            ['email' => 'binh.tran@example.com', 'text' => 'Nhớ mở firewall cho 8081 nếu chạy Windows, tránh bị chặn.'],
            ['email' => 'chi.pham@example.com', 'text' => 'Mình sẽ viết FAQ ngắn về lỗi port, cache, và cách đổi cổng.'],

            // Wrap-up
            ['email' => 'anh.nguyen@example.com', 'text' => 'Mình sẽ chốt demo build tối nay, ai cần thêm data báo mình.'],
            ['email' => 'binh.tran@example.com', 'text' => 'Ok, mình giữ worker chạy để QA test realtime.'],
            ['email' => 'chi.pham@example.com', 'text' => 'Cảm ơn, để mình kiểm tra thêm trường hợp mất mạng giữa chừng.'],
        ];

        $start = Carbon::now()->subMinutes(count($dialogue));

        Model::unguarded(function () use ($dialogue, $users, $start) {
            foreach ($dialogue as $index => $entry) {
                $user = $users[$entry['email']] ?? null;
                if (! $user) {
                    continue; // skip if user missing; should not happen
                }

                $timestamp = $start->copy()->addMinutes($index);

                Message::create([
                    'user_id' => $user->id,
                    'text' => $entry['text'],
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }
        });
    }
}
