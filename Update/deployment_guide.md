# Hướng Dẫn Triển Khai Hệ Thống Mái Nhà Xanh Lên Cloud (Railway / VPS) (Tuần 3)

Tài liệu này hướng dẫn chi tiết từng bước (Step-by-Step) để đưa dự án **Mái Nhà Xanh** từ môi trường lập trình nội bộ (**localhost**) lên chạy trực tuyến trên nền tảng đám mây **Railway.app** hoặc máy chủ ảo **VPS (Ubuntu)** sử dụng Docker.

---

## PHẦN A: TRIỂN KHAI LÊN CLOUD RAILWAY.APP (KHUYẾN NGHỊ)

Railway.app là nền tảng đám mây PaaS cực kỳ hiện đại, hỗ trợ build tự động từ GitHub thông qua tệp `Dockerfile` chúng tôi đã cung cấp sẵn.

### Bước 1: Chuẩn bị mã nguồn
1. Đảm bảo toàn bộ mã nguồn của bạn (bao gồm tệp `Dockerfile` mới tạo ở root) đã được đẩy (push) lên một kho chứa **GitHub** (chế độ Public hoặc Private).
2. Kiểm tra xem tệp tin `.env` đã được loại bỏ khỏi Git (nằm trong `.gitignore`) để tránh lộ khóa bảo mật.

### Bước 2: Tạo dự án mới và CSDL MySQL trên Railway
1. Truy cập [Railway.app](https://railway.app) và đăng ký/đăng nhập bằng tài khoản GitHub.
2. Nhấn nút **New Project** -> Chọn **Provision MySQL**.
3. Railway sẽ tạo cho bạn một cụm dịch vụ MySQL trống. Chờ vài giây để hệ thống khởi chạy, sau đó bấm vào ô dịch vụ MySQL này:
   * Chuyển sang tab **Variables**: Sao chép các thông tin kết nối gồm: `MYSQLHOST`, `MYSQLDATABASE`, `MYSQLUSER`, `MYSQLPASSWORD`, `MYSQLPORT`.
   * Chuyển sang tab **Connect**: Sử dụng phần mềm quản lý database (như TablePlus, DBeaver) kết nối từ xa vào CSDL này bằng thông số trên, sau đó chạy import tệp tin dữ liệu `.sql` của bạn.

### Bước 3: Triển khai mã nguồn Web App
1. Trên giao diện Dashboard của Railway, nhấn **New** -> Chọn **GitHub Repo**.
2. Chọn kho chứa GitHub chứa mã nguồn dự án của bạn.
3. Railway sẽ tự động phát hiện tệp tin `Dockerfile` ở root và tiến hành quá trình Build & Deploy tự động.

### Bước 4: Cấu hình biến môi trường (Environment Variables)
Khi ứng dụng được deploy lên Railway, bạn cần cấu hình các thông số bảo mật. Bấm vào dịch vụ Web vừa tạo trên giao diện Railway, chọn tab **Variables** và nhấn **New Variable** để thêm đầy đủ các biến sau:
* **`DB_HOST`**: Điền `${{MySQL.MYSQLHOST}}` (Railway tự động tham chiếu cổng host CSDL).
* **`DB_NAME`**: Điền `${{MySQL.MYSQLDATABASE}}`.
* **`DB_USER`**: Điền `${{MySQL.MYSQLUSER}}`.
* **`DB_PASS`**: Điền `${{MySQL.MYSQLPASSWORD}}`.
* **`APP_DEBUG`**: Điền `false` (Đảm bảo an toàn, ẩn lỗi PHP khi chạy thực tế).
* **`ENABLE_RATE_LIMIT`**: Điền `true` (Kích hoạt bảo vệ chặn IP khi đăng nhập sai).
* **`GROQ_API_KEY`**: Điền API key Groq của bạn.
* **`GEMINI_API_KEY`**: Điền API key Gemini của bạn.
* **`ONESIGNAL_APP_ID`**: Điền ID ứng dụng OneSignal.
* **`ONESIGNAL_REST_API_KEY`**: Điền REST API key của OneSignal.

*Lưu ý: Sau khi lưu biến môi trường, Railway sẽ tự động Re-deploy ứng dụng trong vài giây.*

### Bước 5: Cài đặt Tên miền (Domain) và SSL
1. Chọn tab **Settings** của dịch vụ Web.
2. Tìm phần **Domains** -> Chọn **Generate Domain** (Để sử dụng tên miền phụ miễn phí của Railway dạng `xxx.up.railway.app`) hoặc nhập tên miền riêng của bạn nếu có.
3. Railway sẽ tự động cấu hình và kích hoạt chứng chỉ SSL Let's Encrypt trong vòng 1-2 phút. Bây giờ bạn đã có thể truy cập dự án trực tuyến qua giao diện HTTPS!

---

## PHẦN B: TRIỂN KHAI LÊN VPS (UBUNTU SERVER) BẰNG DOCKER COMPOSE

Phương án này phù hợp khi bạn tự thuê một máy chủ ảo riêng (như Vultr, DigitalOcean, AWS) chạy hệ điều hành Ubuntu Server.

### Bước 1: Cài đặt Docker & Docker Compose trên VPS
Kết nối SSH vào VPS của bạn và chạy các lệnh cài đặt sau:
```bash
sudo apt update && sudo apt upgrade -y
sudo apt install docker.io docker-compose -y
sudo systemctl enable --now docker
```

### Bước 2: Clone mã nguồn và cấu hình biến môi trường
1. Clone dự án từ GitHub về thư mục trên VPS:
   ```bash
   git clone <URL_GITHUB_CUA_BAN> /var/www/mai-nha-xanh
   cd /var/www/mai-nha-xanh
   ```
2. Tạo tệp `.env` thực tế từ tệp mẫu:
   ```bash
   cp .env.example .env
   nano .env
   ```
   *Điền chính xác các biến môi trường kết nối CSDL local docker, API keys của bạn vào file này rồi lưu lại (`Ctrl+O`, `Enter`, `Ctrl+X`).*

### Bước 3: Khởi chạy Containers bằng Docker Compose
Khởi động hệ thống thông qua lệnh Docker Compose:
```bash
sudo docker-compose up -d --build
```
*Lệnh này sẽ tự động tải các image cần thiết, build Dockerfile web, tạo CSDL MySQL riêng biệt và chạy ẩn (`-d`) trên server.*

### Bước 4: Import dữ liệu MySQL
1. Copy file `.sql` dữ liệu của bạn vào container MySQL:
   ```bash
   sudo docker cp data_backup.sql $(sudo docker ps -aqf "name=db"):/data_backup.sql
   ```
2. Thực hiện Import SQL vào database:
   ```bash
   sudo docker exec -it $(sudo docker ps -aqf "name=db") mysql -u root -prootpassword quanlytro -e "source /data_backup.sql"
   ```

### Bước 5: Cấu hình Nginx Reverse Proxy & SSL Let's Encrypt
Để trỏ tên miền về cổng `8080` của docker và cấp phát HTTPS, bạn cài đặt Nginx trên hệ điều hành VPS:
1. Cài đặt Nginx và Certbot:
   ```bash
   sudo apt install nginx certbot python3-certbot-nginx -y
   ```
2. Tạo cấu hình ảo hóa Nginx:
   ```bash
   sudo nano /etc/nginx/sites-available/mainhaxanh
   ```
   Thêm cấu hình sau (thay đổi `yourdomain.com` thành domain thật):
   ```nginx
   server {
       listen 80;
       server_name yourdomain.com www.yourdomain.com;

       location / {
           proxy_pass http://127.0.0.1:8080;
           proxy_set_header Host $host;
           proxy_set_header X-Real-IP $remote_addr;
           proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
           proxy_set_header X-Forwarded-Proto $scheme;
       }
   }
   ```
3. Kích hoạt cấu hình và reload Nginx:
   ```bash
   sudo ln -s /etc/nginx/sites-available/mainhaxanh /etc/nginx/sites-enabled/
   sudo nginx -t
   sudo systemctl restart nginx
   ```
4. Cài đặt chứng chỉ SSL Let's Encrypt:
   ```bash
   sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com
   ```
   *Chọn option tự động chuyển hướng HTTP sang HTTPS (Redirect).*

Hệ thống của bạn lúc này đã online cực kỳ an toàn và ổn định trên VPS!
