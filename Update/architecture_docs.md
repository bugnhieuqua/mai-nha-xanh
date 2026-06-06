# Tài Liệu Đặc Tả Sơ Đồ Kiến Trúc Hệ Thống — Mái Nhà Xanh (Tuần 3)

Tài liệu này cung cấp toàn bộ các sơ đồ đặc tả kỹ thuật của hệ thống Mái Nhà Xanh phục vụ Kỳ thi Quốc gia. Các sơ đồ được vẽ trực quan bằng ngôn ngữ Mermaid.

---

## 1. Sơ Đồ Kiến Trúc Hệ Thống (System Architecture)

Sơ đồ mô tả luồng tương tác từ trình duyệt của người dùng (PWA Client) qua mạng internet, đi qua các lớp bảo mật, vào máy chủ PHP Apache và tương tác với cơ sở dữ liệu cùng các API dịch vụ bên thứ ba.

```mermaid
graph TD
    subgraph Client ["Client-Side (Trình duyệt & PWA)"]
        A["PWA App (HTML5/CSS3/JS)"]
        A1["assets/js/assistant.js"]
        A2["Service Worker (sw.php)"]
        A3["Leaflet Map API"]
    end

    subgraph Security ["Lớp Bảo Mật & Định Tuyến"]
        B[".htaccess (CSP & Security Headers)"]
        B1["WAF Bypass Handler (Base64 Encode)"]
        B2["CSRF & Session Hijacking Validator"]
    end

    subgraph Server ["Server-Side (PHP Core Backend)"]
        C["config/bootstrap.php (Unified Loader)"]
        C1["includes/error_handler.php (Silent Logger)"]
        
        subgraph API_v2 ["RESTful API Gateway v2"]
            D1["api/v2/auth.php"]
            D2["api/v2/rooms.php"]
            D3["api/v2/rooms_search.php"]
            D4["api/v2/chat.php"]
        end
        
        subgraph Legacy_API ["Legacy APIs"]
            E1["api/dangbai.php (AI Mod)"]
            E2["api/rate_limit.php"]
        end
    end

    subgraph Storage ["Database Layer"]
        F[("MySQL Database (quanlytro)")]
    end

    subgraph External ["Dịch vụ bên thứ ba (APIs)"]
        G1["Groq API (Llama 3.3 70B)"]
        G2["Google Gemini API (Vision Mod)"]
        G3["OneSignal Push Notification"]
        G4["OpenStreetMap (Map Tiles)"]
    end

    %% Client flows
    A -->|1. Request| B
    A1 -->|Bypass WAF| B1
    B1 -->|2. Route to API| API_v2
    B -->|Parse Assets| A2
    A3 -->|Fetch OSM Tiles| G4

    %% Server Flows
    API_v2 --> C
    Legacy_API --> C
    C --> C1
    C -->|Authenticate Session| B2

    %% DB Flows
    API_v2 -->|Query/Insert| F
    Legacy_API -->|Query/Insert| F

    %% Third-party flows
    D4 -->|RAG Extraction/Completions| G1
    D3 -->|Entity Extraction| G1
    E1 -->|AI Moderation| G2
    D2 -->|AI Post Inspection| G2
    API_v2 -->|Send WebPush| G3
```

---

## 2. Sơ Đồ Thực Thể Cơ Sở Dữ Liệu (Database ERD)

Sơ đồ mô tả cấu trúc quan hệ thực thể (ERD) giữa 9 bảng dữ liệu cốt lõi trong hệ thống `quanlytro`.

```mermaid
erDiagram
    users {
        int id PK
        string username
        string password
        string email
        string hoten
        string sdt
        enum status "active / banned"
        enum role "admin / user"
        string avatar
        datetime created_at
    }

    phongtro {
        int id PK
        string ten_phong
        string mota
        string hinhanh
        decimal gia
        double dientich
        string diachi
        string tiennghi
        datetime ngaydang
        enum trangthai "con_phong / da_coc / da_thue"
        double lat
        double lng
    }

    dangbai_chothuetro {
        int id PK
        int user_id FK
        string tieude
        string mota
        string hinhanh
        text hinhanh_list
        string video
        decimal gia
        double dientich
        string diachi
        string tiennghi
        string ten_chunha
        string sdt_chunha
        datetime ngaydang
        enum trangthai "cho_duyet / da_duyet / tu_choi"
        enum trangthai_phong "con_phong / da_coc / da_thue"
        text ai_check
        double lat
        double lng
        datetime duyet_luc
    }

    dat_phong {
        int id PK
        int user_id FK
        int phong_id
        datetime ngay_dat
        string tin_nhan
        string trang_thai
        string nguon_phong "phongtro / dangbai"
        datetime created_at
    }

    reports {
        int id PK
        int user_id FK
        int phong_id
        string nguon_phong "phongtro / dangbai"
        string loai_vi_pham
        string mota
        string trang_thai "cho_xu_ly / da_xu_ly"
        datetime created_at
    }

    community_posts {
        int id PK
        int user_id FK
        text content
        string media_url
        datetime created_at
    }

    community_comments {
        int id PK
        int post_id FK
        int user_id FK
        int parent_id FK
        text content
        datetime created_at
    }

    notifications {
        int id PK
        int user_id FK
        string title
        text message
        boolean is_read
        string type
        datetime created_at
    }

    chatbot_history {
        int id PK
        string session_id
        text user_message
        text bot_response
        datetime created_at
    }

    %% Relationships
    users ||--o{ dangbai_chothuetro : "đăng bài"
    users ||--o{ dat_phong : "đặt phòng"
    users ||--o{ reports : "gửi báo cáo"
    users ||--o{ community_posts : "đăng bài viết"
    users ||--o{ community_comments : "bình luận"
    users ||--o{ notifications : "nhận thông báo"
    
    dangbai_chothuetro ||--o{ dat_phong : "được đặt"
    dangbai_chothuetro ||--o{ reports : "bị báo cáo"
    phongtro ||--o{ dat_phong : "được đặt"
    phongtro ||--o{ reports : "bị báo cáo"
    
    community_posts ||--o{ community_comments : "chứa bình luận"
    community_comments ||--o{ community_comments : "bình luận phản hồi (phân cấp)"
```

---

## 3. Sơ Đồ Đường Ống AI & RAG Chatbot (AI Pipeline Flow)

Sơ đồ đặc tả chi tiết cách thức Chatbot hoạt động theo mô hình RAG (Retrieval-Augmented Generation) và tự động lọc dữ liệu an toàn.

```mermaid
sequenceDiagram
    autonumber
    actor User as Người dùng (Client PWA)
    participant JS as Frontend (assistant.js)
    participant API as Chat API (v2/chat.php)
    participant Groq as Groq AI (Llama 3.3)
    participant DB as MySQL Database

    User->>JS: Nhập câu hỏi (Ví dụ: "tìm phòng ở Hưng Dũng có máy lạnh dưới 2tr")
    JS->>JS: Encode câu hỏi bằng Base64 (Bypass WAF của nhà mạng)
    JS->>API: POST request (JSON payload / Base64 string)
    API->>API: Decode Base64 lấy tin nhắn người dùng
    API->>Groq: Gửi câu hỏi yêu cầu trích xuất tiêu chí (Structured Extraction)
    Note over API,Groq: Trích xuất dạng JSON: { ward: "Hưng Dũng", max_price: 2000000, amenities: ["máy lạnh"] }
    Groq-->>API: Trả về JSON tiêu chí lọc
    API->>DB: Thực hiện SQL Query lọc các phòng thỏa mãn tiêu chí
    DB-->>API: Trả về danh sách phòng trọ khớp thực tế (5-8 bài đăng)
    API->>API: Nhúng danh sách phòng và link dạng [ROOM:nguon:id] vào System Prompt RAG
    API->>Groq: Gửi RAG System Prompt + Lịch sử chat (Context Injection)
    Groq-->>API: Tạo câu trả lời chi tiết chính xác dựa trên danh sách phòng thực tế
    API-->>JS: Trả phản hồi JSON (Chuẩn định dạng OpenAI)
    JS->>JS: Quét nội dung câu trả lời, convert thẻ [ROOM:nguon:id] sang Room Card HTML
    JS->>User: Hiển thị câu trả lời dạng chat bong bóng + Khung Card thông tin phòng có thể tương tác
```

---

## 4. Sơ Đồ Quy Trình Triển Khai & Vận Hành (CI/CD & Deployment)

Sơ đồ mô tả quy trình triển khai tự động từ máy lập trình viên qua nền tảng ảo hóa Docker lên máy chủ đám mây Railway:

```mermaid
graph LR
    Dev["1. Developer (Local)"] -->|Git Push| GitHub["2. GitHub Repository"]
    GitHub -->|3. Webhook Trigger| Railway["4. Railway Cloud Platform"]
    
    subgraph Railway_Build ["Hạ tầng Railway.app"]
        direction TB
        RB_1["Tải mã nguồn"] --> RB_2["Đọc Dockerfile"]
        RB_2 --> RB_3["Build Docker Image"]
        RB_3 --> RB_4["Đẩy Image vào Container Registry"]
    end
    
    Railway --> Railway_Build
    
    subgraph Railway_Run ["Môi trường Chạy thực tế"]
        direction TB
        RR_1["Chạy Container Web App (PHP Apache)"]
        RR_2["Chạy Container Database (MySQL 8.0)"]
        RR_3["Cấu hình Env Variables (.env)"]
        RR_4["Cấp phát SSL tự động (Let's Encrypt)"]
        
        RR_1 <-->|Kết nối nội bộ| RR_2
        RR_3 --> RR_1
    end
    
    Railway_Build -->|5. Deploy Container| Railway_Run
    
    Uptime["UptimeRobot Uptime Monitor"] -.->|6. Healthcheck mỗi 5 phút| RR_4
```
