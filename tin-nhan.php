<?php
require_once 'config/bootstrap.php';

// Bảo vệ trang: chỉ cho phép người dùng đã đăng nhập truy cập
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$page_title = "Tin Nhắn & Gọi Điện";
require_once 'includes/header.php';
?>

<style>
/* Disable global scrollbar & force full-height */
html, body {
    overflow: hidden !important;
    height: 100% !important;
    margin: 0 !important;
}

/* Hide standard footer, floating button, chatbot and chatadmin on chat page */
.footer,
.floating-post-btn,
.admin-chat-wrapper,
.admin-chat-popup,
#chatbot-toggler,
.chatbot-popup {
    display: none !important;
}

:root {
    --messenger-bg: #fff;
    --messenger-border: #f1f5f9;
    --messenger-active-bg: #ecfdf5;
    --messenger-hover-bg: #f8fafc;
    --primary-color: #10b981;
    --text-primary: #1e293b;
    --text-secondary: #64748b;
    --chat-bubble-self: #10b981;
    --chat-bubble-other: #f1f5f9;
}

/* --- Dark Mode Theme Styles --- */
[data-theme="dark"] .messenger-layout {
    --messenger-bg: #0f172a;
    --messenger-border: #1e293b;
    --messenger-active-bg: #1e293b;
    --messenger-hover-bg: #1e293b;
    --text-primary: #f8fafc;
    --text-secondary: #94a3b8;
    --chat-bubble-other: #1e293b;
    background: #0f172a !important;
}
[data-theme="dark"] .msg-sidebar {
    background: #0f172a !important;
    border-right: 1px solid #1e293b !important;
}
[data-theme="dark"] .msg-sidebar-header {
    border-bottom: 1px solid #1e293b !important;
}
[data-theme="dark"] .sidebar-title {
    color: #f8fafc !important;
}
[data-theme="dark"] .sidebar-icon-btn {
    background: #1e293b !important;
    color: #f8fafc !important;
}
[data-theme="dark"] .sidebar-search-input {
    background: #1e293b !important;
    border: 1px solid #334155 !important;
    color: #f8fafc !important;
}
[data-theme="dark"] .sidebar-tab {
    background: #1e293b !important;
    color: #94a3b8 !important;
}
[data-theme="dark"] .sidebar-tab.active {
    background: rgba(16, 185, 129, 0.2) !important;
    color: #10b981 !important;
}
[data-theme="dark"] .contact-item {
    border-bottom: 1px solid #1e293b !important;
}
[data-theme="dark"] .contact-item:hover {
    background: #1e293b !important;
}
[data-theme="dark"] .contact-item.active {
    background: #1e293b !important;
}
[data-theme="dark"] .contact-item h4 {
    color: #f8fafc !important;
}
[data-theme="dark"] .msg-chat-area {
    background: #0f172a !important;
}
[data-theme="dark"] .msg-chat-header {
    background: #0f172a !important;
    border-bottom: 1px solid #1e293b !important;
}
[data-theme="dark"] .chat-header-name {
    color: #f8fafc !important;
}
[data-theme="dark"] .pinned-bar {
    background: #0f172a !important;
    border-bottom: 1px solid #1e293b !important;
    color: #f8fafc !important;
}
[data-theme="dark"] .msg-chat-history {
    background: #0b0f19 !important;
}
[data-theme="dark"] .msg-input-area {
    background: #0f172a !important;
    border-top: 1px solid #1e293b !important;
}
[data-theme="dark"] .msg-input-field {
    background: #1e293b !important;
    border: 1px solid #334155 !important;
    color: #f8fafc !important;
}
[data-theme="dark"] .msg-input-field:focus {
    background: #1e293b !important;
    border-color: #10b981 !important;
}
[data-theme="dark"] .msg-empty-state {
    background: #0f172a !important;
}
[data-theme="dark"] .empty-state-title {
    color: #f8fafc !important;
}
[data-theme="dark"] .empty-state-desc {
    color: #94a3b8 !important;
}
[data-theme="dark"] .msg-info-panel {
    background: #0f172a !important;
    border-left: 1px solid #1e293b !important;
}
[data-theme="dark"] .info-panel-header {
    border-bottom: 1px solid #1e293b !important;
}
[data-theme="dark"] .info-panel-name {
    color: #f8fafc !important;
}
[data-theme="dark"] .info-quick-btn {
    background: #1e293b !important;
    color: #f8fafc !important;
}
[data-theme="dark"] .accordion-header {
    background: #0f172a !important;
    color: #f8fafc !important;
    border-bottom: 1px solid #1e293b !important;
}
[data-theme="dark"] .accordion-body {
    background: #0f172a !important;
}
[data-theme="dark"] .accordion-action-btn {
    color: #cbd5e1 !important;
}
[data-theme="dark"] .accordion-action-btn:hover {
    background: #1e293b !important;
}
[data-theme="dark"] .info-member-name {
    color: #cbd5e1 !important;
}
/* Dark mode chat bubbles */
[data-theme="dark"] {
    --chat-bubble-bg-received: #1e293b !important;
    --chat-bubble-color-received: #e2e8f0 !important;
}
/* Bubble nhận chế độ tối */
[data-theme="dark"] .message-row.received .message-bubble-container > div:first-child,
[data-theme="dark"] .message-bubble-container {
    --chat-bubble-bg-received: #1e293b;
    --chat-bubble-color-received: #e2e8f0;
}
/* Thanh cuộn chế độ tối */
[data-theme="dark"] .msg-chat-history::-webkit-scrollbar-track {
    background: #0b0f19 !important;
}
[data-theme="dark"] .msg-chat-history::-webkit-scrollbar-thumb {
    background: rgba(16, 185, 129, 0.3) !important;
}
[data-theme="dark"] .pinned-text,
[data-theme="dark"] .pinned-label {
    color: #f8fafc !important;
}
/* Giữ khoảng cách bubble chuẩn */
.message-row {
    margin: 4px 0;
}
.message-row.sent .message-bubble-container {
    flex-direction: row-reverse;
}

/* Container */
.messenger-layout {
    display: flex;
    width: 100%;
    height: 100% !important;
    margin-top: 0px !important;
    background: var(--messenger-bg);
    overflow: hidden;
    position: relative;
    font-family: 'Outfit', 'Inter', sans-serif;
}

/* Sidebar */
.msg-sidebar {
    width: 360px;
    border-right: 1px solid var(--messenger-border);
    display: flex;
    flex-direction: column;
    background: #fff;
    flex-shrink: 0;
}

.msg-sidebar-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--messenger-border);
}

.sidebar-title-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.sidebar-title {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--text-primary);
    margin: 0;
}

.sidebar-actions {
    display: flex;
    gap: 8px;
}

.sidebar-icon-btn {
    background: #f1f5f9;
    border: none;
    color: var(--text-primary);
    width: 36px;
    height: 36px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.sidebar-icon-btn:hover {
    background: #e2e8f0;
}

.sidebar-search-wrap {
    position: relative;
}

.sidebar-search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
    font-size: 0.9rem;
}

.sidebar-search-input {
    width: 100%;
    padding: 10px 16px 10px 38px;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    font-size: 0.88rem;
    outline: none;
    background: #f8fafc;
    color: var(--text-primary);
    transition: all 0.2s;
}

.sidebar-search-input:focus {
    border-color: var(--primary-color);
    background: #fff;
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.15);
}

.sidebar-tabs {
    display: flex;
    gap: 8px;
    margin-top: 12px;
}

.sidebar-tab {
    padding: 6px 16px;
    border-radius: 20px;
    border: none;
    background: #f1f5f9;
    color: var(--text-secondary);
    font-weight: 600;
    font-size: 0.82rem;
    cursor: pointer;
    transition: all 0.2s;
}

.sidebar-tab.active {
    background: rgba(16, 185, 129, 0.1);
    color: var(--primary-color);
}

.msg-contact-list {
    flex: 1;
    overflow-y: auto;
    padding: 8px 0;
}

.contacts-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px;
    color: var(--text-secondary);
    font-size: 0.88rem;
}

/* Chat Area */
.msg-chat-area {
    flex: 1;
    display: flex;
    flex-direction: column;
    background: #f8fafc;
    position: relative;
    height: 100%;
    min-width: 0;
}

.msg-chat-header {
    padding: 16px 24px;
    border-bottom: 1px solid var(--messenger-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #fff;
    z-index: 10;
    height: 77px;
}

.chat-header-left {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
}

.chat-back-btn {
    background: none;
    border: none;
    color: var(--primary-color);
    font-size: 1.2rem;
    cursor: pointer;
    padding: 8px;
    display: none;
    align-items: center;
    justify-content: center;
}

.chat-header-avatar-wrap {
    position: relative;
    flex-shrink: 0;
}

.chat-header-avatar {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    object-fit: cover;
}

.chat-header-status-dot {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.15);
}

.chat-header-name {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 2px 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.chat-header-status {
    font-size: 0.78rem;
    color: var(--text-secondary);
}

.chat-header-actions {
    display: flex;
    gap: 8px;
    align-items: center;
}

.chat-action-btn {
    background: none;
    border: none;
    color: var(--primary-color);
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    transition: all 0.2s;
}

.chat-action-btn:hover {
    background: #f1f5f9;
}

.chat-action-btn.btn-follow {
    font-size: 0.85rem;
    font-weight: 600;
    border: 1px solid var(--primary-color);
    border-radius: 20px;
    width: auto;
    padding: 0 16px;
    height: 36px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.chat-action-btn.btn-follow:hover {
    background: rgba(16, 185, 129, 0.05);
}

/* Pinned Bar */
.pinned-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #fff;
    border-bottom: 1px solid var(--messenger-border);
    padding: 8px 24px;
    font-size: 0.82rem;
    color: var(--text-primary);
    z-index: 9;
}

.pinned-bar-left {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
    flex: 1;
}

.pinned-icon {
    color: var(--primary-color);
    font-size: 0.9rem;
    transform: rotate(45deg);
}

.pinned-bar-content {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.pinned-label {
    font-weight: 700;
    color: var(--primary-color);
    font-size: 0.75rem;
}

.pinned-text {
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: pointer;
    font-weight: 500;
}

.pinned-unpin-btn {
    background: none;
    border: none;
    color: #ef4444;
    cursor: pointer;
    padding: 4px;
}

/* Chat History */
.msg-chat-history {
    flex: 1;
    overflow-y: auto;
    padding: 16px 24px;
    display: flex;
    flex-direction: column;
    justify-content: flex-start;
    gap: 2px;
    background: #f8fafc;
    min-height: 0;
}

/* Input Area */
.msg-input-area {
    padding: 16px 24px;
    border-top: 1px solid var(--messenger-border);
    display: flex;
    align-items: center;
    gap: 12px;
    background: #fff;
}

.msg-input-actions-left,
.msg-input-actions-right {
    display: flex;
    gap: 4px;
}

.msg-input-action-btn,
.msg-like-btn {
    background: none;
    border: none;
    color: var(--primary-color);
    width: 36px;
    height: 36px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
    transition: all 0.2s;
}

.msg-input-action-btn:hover,
.msg-like-btn:hover {
    background: #f1f5f9;
}

.msg-input-wrap {
    flex: 1;
}

.msg-input-field {
    width: 100%;
    padding: 10px 20px;
    border-radius: 20px;
    border: 1px solid #e2e8f0;
    font-size: 0.92rem;
    outline: none;
    background: #f1f5f9;
    color: var(--text-primary);
    transition: all 0.2s;
}

.msg-input-field:focus {
    border-color: var(--primary-color);
    background: #fff;
}

.msg-send-btn {
    background: var(--primary-color);
    border: none;
    color: #fff;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    transition: all 0.2s;
}

.msg-send-btn:hover {
    transform: scale(1.05);
    background: #059669;
}

/* Empty State */
.msg-empty-state {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 40px;
    text-align: center;
    background: #fff;
}

.empty-state-icon {
    font-size: 4rem;
    margin-bottom: 20px;
    animation: floatIcon 3s ease-in-out infinite;
}

.empty-state-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 8px;
}

.empty-state-desc {
    color: var(--text-secondary);
    font-size: 0.9rem;
    max-width: 360px;
    margin-bottom: 20px;
}

.empty-state-btn {
    background: var(--primary-color);
    color: #fff;
    border: none;
    padding: 10px 20px;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s;
}

.empty-state-btn:hover {
    background: #059669;
}

/* Info Panel */
.msg-info-panel {
    width: 320px;
    border-left: 1px solid var(--messenger-border);
    display: flex;
    flex-direction: column;
    background: #fff;
    flex-shrink: 0;
    overflow-y: auto;
}

.info-panel-top {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 32px 24px;
    border-bottom: 1px solid var(--messenger-border);
    text-align: center;
}

.info-panel-avatar-wrap {
    position: relative;
    margin-bottom: 16px;
}

.info-panel-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #fff;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.info-panel-dot {
    position: absolute;
    bottom: 4px;
    right: 4px;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 2px solid #fff;
}

.info-panel-name {
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 4px 0;
}

.info-panel-status {
    font-size: 0.8rem;
    color: var(--text-secondary);
    margin-bottom: 16px;
}

.info-panel-quick-actions {
    display: flex;
    justify-content: center;
    gap: 20px;
}

.info-quick-btn-wrap {
    display: flex;
    flex-direction: column;
    align-items: center;
    font-size: 0.75rem;
    color: var(--text-secondary);
    gap: 4px;
}

.info-quick-btn {
    background: #f1f5f9;
    border: none;
    color: var(--text-primary);
    width: 36px;
    height: 36px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.95rem;
    transition: all 0.2s;
}

.info-quick-btn:hover {
    background: #e2e8f0;
}

/* Accordion */
.info-accordion {
    padding: 12px;
}

.accordion-item {
    border-bottom: 1px solid var(--messenger-border);
}

.accordion-item:last-child {
    border-bottom: none;
}

.accordion-header {
    width: 100%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 12px;
    background: none;
    border: none;
    font-weight: 600;
    color: var(--text-primary);
    font-size: 0.88rem;
    cursor: pointer;
    transition: background 0.2s;
    border-radius: 8px;
}

.accordion-header:hover {
    background: #f8fafc;
}

.accordion-chevron {
    font-size: 0.8rem;
    color: var(--text-secondary);
    transition: transform 0.2s;
}

.accordion-body {
    padding: 8px 12px 16px 12px;
}

.accordion-row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 0;
}

.accordion-row-icon {
    color: var(--text-secondary);
    font-size: 1rem;
}

.accordion-row-label {
    font-size: 0.75rem;
    color: var(--text-secondary);
}

.accordion-row-val {
    font-size: 0.88rem;
    font-weight: 600;
    color: var(--text-primary);
}

.accordion-action-btn {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 12px;
    background: none;
    border: none;
    font-size: 0.85rem;
    color: var(--text-primary);
    cursor: pointer;
    border-radius: 8px;
    font-weight: 500;
    transition: background 0.2s;
}

.accordion-action-btn:hover {
    background: #f8fafc;
}

.accordion-action-btn.danger-btn {
    color: #ef4444;
}

.accordion-action-btn.danger-btn:hover {
    background: rgba(239, 68, 68, 0.05);
}

/* Members list inside Info Panel */
.info-members-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-height: 180px;
    overflow-y: auto;
}

.info-member-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 0;
}

.info-member-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
}

.info-member-name {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-primary);
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.info-member-role {
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 10px;
    font-weight: 600;
}

.info-member-role.role-owner {
    background: rgba(16, 185, 129, 0.1);
    color: var(--primary-color);
}

.info-member-role.role-admin {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

/* Custom Scrollbars */
.msg-contact-list::-webkit-scrollbar,
.msg-chat-history::-webkit-scrollbar,
.msg-info-panel::-webkit-scrollbar {
    width: 6px;
}
.msg-contact-list::-webkit-scrollbar-thumb,
.msg-chat-history::-webkit-scrollbar-thumb,
.msg-info-panel::-webkit-scrollbar-thumb {
    background: rgba(16, 185, 129, 0.2);
    border-radius: 10px;
}
.msg-contact-list::-webkit-scrollbar-thumb:hover,
.msg-chat-history::-webkit-scrollbar-thumb:hover,
.msg-info-panel::-webkit-scrollbar-thumb:hover {
    background: rgba(16, 185, 129, 0.4);
}

/* Hidden trackers */
.status-dot.online { background-color: #10b981 !important; }
.status-dot.offline { background-color: #94a3b8 !important; }

/* Responsive */
@media (max-width: 900px) {
    .msg-info-panel {
        display: none !important;
    }
}

@media (max-width: 768px) {
    .messenger-layout {
        margin-top: 0px !important;
        height: 100% !important;
    }
    
    .msg-sidebar {
        width: 100%;
    }
    
    .msg-chat-area {
        display: none;
    }
    
    .messenger-layout.in-chat .msg-sidebar {
        display: none;
    }
    
    .messenger-layout.in-chat .msg-chat-area {
        display: flex;
    }
    
    .chat-back-btn {
        display: block !important;
    }
/* Custom Action Menu Context-Style */
.msg-context-menu {
    position: absolute;
    background: var(--messenger-bg, #ffffff);
    border: 1px solid var(--messenger-border, #e2e8f0);
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    border-radius: 12px;
    padding: 6px;
    z-index: 10000;
    min-width: 140px;
    display: none;
    flex-direction: column;
    gap: 2px;
}
[data-theme="dark"] .msg-context-menu {
    background: #1e293b;
    border-color: #334155;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
}
.msg-context-menu-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    font-size: 0.88rem;
    font-weight: 500;
    color: var(--text-primary, #1e293b);
    border: none;
    background: transparent;
    border-radius: 8px;
    cursor: pointer;
    text-align: left;
    width: 100%;
    transition: all 0.15s ease;
}
.msg-context-menu-item:hover {
    background: rgba(16, 185, 129, 0.1) !important;
    color: #10b981 !important;
}
.msg-context-menu-item.danger {
    color: #ef4444 !important;
}
.msg-context-menu-item.danger:hover {
    background: rgba(239, 68, 68, 0.1) !important;
    color: #ef4444 !important;
}
.msg-more-trigger {
    background: none;
    border: none;
    color: #94a3b8;
    cursor: pointer;
    padding: 4px 6px;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    width: 28px;
    height: 28px;
    transition: all 0.2s ease;
}
.msg-more-trigger:hover {
    background: rgba(16, 185, 129, 0.1) !important;
    color: #10b981 !important;
}
}
</style>

<!-- Full-screen Messenger-style 3-column chat layout -->
<div class="messenger-layout" id="messenger-layout">

    <!-- ════ CỘT TRÁI: SIDEBAR ════ -->
    <div class="msg-sidebar" id="msg-sidebar">
        <div class="msg-sidebar-header">
            <div class="sidebar-title-row">
                <h2 class="sidebar-title">Chats</h2>
                <div class="sidebar-actions">
                    <button class="sidebar-icon-btn" title="Tạo nhóm mới" onclick="openCreateGroupModal()">
                        <i class="fas fa-edit"></i>
                    </button>
                </div>
            </div>
            <div class="sidebar-search-wrap">
                <i class="fas fa-search sidebar-search-icon"></i>
                <input type="text" id="contact-search" class="sidebar-search-input" placeholder="Tìm kiếm...">
            </div>
            <div class="sidebar-tabs" id="sidebar-tabs">
                <button class="sidebar-tab active" data-tab="all" onclick="switchTab('all', this)">Tất cả</button>
                <button class="sidebar-tab" data-tab="unread" onclick="switchTab('unread', this)">Chưa đọc</button>
                <button class="sidebar-tab" data-tab="groups" onclick="switchTab('groups', this)">Nhóm</button>
            </div>
        </div>
        <div id="contacts-list-container" class="msg-contact-list">
            <div class="contacts-loading">
                <i class="fas fa-spinner fa-spin" style="font-size:1.4rem; color:#10b981;"></i>
                <p>Đang tải danh bạ...</p>
            </div>
        </div>
    </div>

    <!-- ════ CỘT GIỮA: CHAT AREA ════ -->
    <div class="msg-chat-area" id="msg-chat-area">

        <!-- Chat Header -->
        <div class="msg-chat-header" id="chat-header" style="display:none;">
            <div class="chat-header-left">
                <button id="btn-back-to-list" class="chat-back-btn" title="Quay lại" style="display:none;">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div class="chat-header-avatar-wrap" onclick="toggleInfoPanel()" style="cursor:pointer;">
                    <img id="partner-avatar" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" alt="Avatar" class="chat-header-avatar">
                    <span id="partner-status-dot" class="status-dot offline chat-header-status-dot"></span>
                </div>
                <div onclick="toggleInfoPanel()" style="cursor:pointer; min-width:0;">
                    <h4 id="partner-name" class="chat-header-name">Đối tác</h4>
                    <span id="partner-status-text" class="chat-header-status">Ngoại tuyến</span>
                </div>
            </div>
            <div class="chat-header-actions">
                <button id="btn-call-partner" class="chat-action-btn" title="Gọi thoại"><i class="fas fa-phone-alt"></i></button>
                <button id="btn-video-call" class="chat-action-btn" title="Gọi video" onclick="startVideoCall(document.getElementById('active-chat-user-id').value, document.getElementById('partner-name').innerText)"><i class="fas fa-video"></i></button>
                <button id="btn-follow-partner" class="chat-action-btn btn-follow" title="Theo dõi"><i class="fas fa-user-plus"></i> Theo dõi</button>
                <button id="btn-group-settings" class="chat-action-btn" title="Quản lý nhóm" onclick="openGroupSettings()" style="display:none;"><i class="fas fa-users-cog"></i></button>
                <button id="btn-toggle-info" class="chat-action-btn" title="Thông tin hội thoại" onclick="toggleInfoPanel()"><i class="fas fa-info-circle"></i></button>
            </div>
        </div>

        <!-- Pinned Message Bar -->
        <div id="pinned-message-bar" class="pinned-bar" style="display:none;">
            <div class="pinned-bar-left">
                <i class="fas fa-thumbtack pinned-icon"></i>
                <div class="pinned-bar-content">
                    <span class="pinned-label">Tin nhắn ghim</span>
                    <span id="pinned-msg-content" class="pinned-text" onclick="scrollToPinnedMessage()">Chưa có</span>
                </div>
            </div>
            <button id="btn-unpin-current" class="pinned-unpin-btn" onclick="unpinCurrentMessage()" title="Bỏ ghim">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Chat History -->
        <div id="chat-history-box" class="msg-chat-history" style="display:none;"></div>

        <!-- Input Area -->
        <div id="chat-input-area" class="msg-input-area" style="display:none; position:relative;">
            <!-- Emoji Picker Panel inside Chat area -->
            <div id="emoji-picker-panel" style="display:none; position:absolute; bottom:80px; left:20px; background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:12px; box-shadow:0 4px 20px rgba(0,0,0,0.15); z-index:100; max-width:280px;">
                <div style="display:grid; grid-template-columns:repeat(7, 1fr); gap:8px; font-size:1.3rem; line-height:1;">
                    <span onclick="insertEmoji('😀')" style="cursor:pointer; text-align:center; padding:4px; hover:background:#f1f5f9; border-radius:6px;">😀</span>
                    <span onclick="insertEmoji('😂')" style="cursor:pointer; text-align:center; padding:4px; hover:background:#f1f5f9; border-radius:6px;">😂</span>
                    <span onclick="insertEmoji('😍')" style="cursor:pointer; text-align:center; padding:4px; hover:background:#f1f5f9; border-radius:6px;">😍</span>
                    <span onclick="insertEmoji('👍')" style="cursor:pointer; text-align:center; padding:4px; hover:background:#f1f5f9; border-radius:6px;">👍</span>
                    <span onclick="insertEmoji('❤️')" style="cursor:pointer; text-align:center; padding:4px; hover:background:#f1f5f9; border-radius:6px;">❤️</span>
                    <span onclick="insertEmoji('🎉')" style="cursor:pointer; text-align:center; padding:4px; hover:background:#f1f5f9; border-radius:6px;">🎉</span>
                    <span onclick="insertEmoji('🤔')" style="cursor:pointer; text-align:center; padding:4px; hover:background:#f1f5f9; border-radius:6px;">🤔</span>
                    <span onclick="insertEmoji('😭')" style="cursor:pointer; text-align:center; padding:4px; hover:background:#f1f5f9; border-radius:6px;">😭</span>
                    <span onclick="insertEmoji('👏')" style="cursor:pointer; text-align:center; padding:4px; hover:background:#f1f5f9; border-radius:6px;">👏</span>
                    <span onclick="insertEmoji('🔥')" style="cursor:pointer; text-align:center; padding:4px; hover:background:#f1f5f9; border-radius:6px;">🔥</span>
                    <span onclick="insertEmoji('😎')" style="cursor:pointer; text-align:center; padding:4px; hover:background:#f1f5f9; border-radius:6px;">😎</span>
                    <span onclick="insertEmoji('😡')" style="cursor:pointer; text-align:center; padding:4px; hover:background:#f1f5f9; border-radius:6px;">😡</span>
                    <span onclick="insertEmoji('🙌')" style="cursor:pointer; text-align:center; padding:4px; hover:background:#f1f5f9; border-radius:6px;">🙌</span>
                    <span onclick="insertEmoji('✨')" style="cursor:pointer; text-align:center; padding:4px; hover:background:#f1f5f9; border-radius:6px;">✨</span>
                </div>
            </div>

            <!-- Hidden File inputs -->
            <input type="file" id="chat-file-input" style="display:none;" onchange="handleChatFileUpload(this, 'file')">
            <input type="file" id="chat-image-input" style="display:none;" accept="image/*" onchange="handleChatFileUpload(this, 'image')">

            <div class="msg-input-actions-left">
                <button class="msg-input-action-btn" title="Đính kèm file" onclick="document.getElementById('chat-file-input').click()"><i class="fas fa-paperclip"></i></button>
                <button class="msg-input-action-btn" title="Chèn ảnh" onclick="document.getElementById('chat-image-input').click()"><i class="fas fa-image"></i></button>
                <button class="msg-input-action-btn" title="Emoji" onclick="toggleEmojiPickerPanel()"><i class="fas fa-smile"></i></button>
            </div>
            <div class="msg-input-wrap">
                <input type="text" id="message-input" class="msg-input-field" placeholder="Aa" onkeydown="if(event.key === 'Enter') sendRealtimeMessage()">
            </div>
            <div class="msg-input-actions-right">
                <button id="btn-send-message" class="msg-send-btn" onclick="sendRealtimeMessage()" title="Gửi">
                    <i class="fas fa-paper-plane"></i>
                </button>
                <button class="msg-like-btn" title="Thả tim" onclick="sendLike()">
                    <i class="fas fa-thumbs-up"></i>
                </button>
            </div>
        </div>

        <!-- Empty State -->
        <div id="chat-empty-state" class="msg-empty-state">
            <div class="empty-state-icon">💬</div>
            <h3 class="empty-state-title">Tin nhắn của bạn</h3>
            <p class="empty-state-desc">Gửi ảnh và tin nhắn riêng tư cho bạn bè hoặc nhóm</p>
            <button onclick="openCreateGroupModal()" class="empty-state-btn">
                <i class="fas fa-plus"></i> Tạo nhóm mới
            </button>
        </div>
    </div>

    <!-- ════ CỘT PHẢI: INFO PANEL ════ -->
    <div class="msg-info-panel" id="msg-info-panel" style="display:none;">
        <div class="info-panel-top">
            <div class="info-panel-avatar-wrap">
                <img id="info-panel-avatar" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" alt="Avatar" class="info-panel-avatar">
                <span id="info-panel-status-dot" class="status-dot offline info-panel-dot"></span>
            </div>
            <h3 id="info-panel-name" class="info-panel-name">—</h3>
            <p id="info-panel-status" class="info-panel-status">Ngoại tuyến</p>
            <div class="info-panel-quick-actions">
                <div class="info-quick-btn-wrap">
                    <button class="info-quick-btn" title="Tắt thông báo"><i class="fas fa-bell-slash"></i></button>
                    <span>Tắt TB</span>
                </div>
                <div class="info-quick-btn-wrap">
                    <button class="info-quick-btn" title="Tìm kiếm trong chat"><i class="fas fa-search"></i></button>
                    <span>Tìm kiếm</span>
                </div>
                <div class="info-quick-btn-wrap" id="info-quick-call" style="display:none;">
                    <button class="info-quick-btn" onclick="document.getElementById('btn-call-partner').click()"><i class="fas fa-phone-alt"></i></button>
                    <span>Gọi</span>
                </div>
                <div class="info-quick-btn-wrap" id="info-quick-video" style="display:none;">
                    <button class="info-quick-btn" onclick="document.getElementById('btn-video-call').click()"><i class="fas fa-video"></i></button>
                    <span>Video</span>
                </div>
            </div>
        </div>

        <div class="info-accordion">
            <!-- Chat info -->
            <div class="accordion-item">
                <button class="accordion-header open" onclick="toggleAccordion(this)">
                    <span>Thông tin hội thoại</span>
                    <i class="fas fa-chevron-down accordion-chevron" style="transform:rotate(180deg);"></i>
                </button>
                <div class="accordion-body" style="display:block;">
                    <div class="accordion-row" id="info-group-name-row" style="display:none;">
                        <i class="fas fa-users accordion-row-icon"></i>
                        <div>
                            <div class="accordion-row-label">Tên nhóm</div>
                            <div id="info-group-name-val" class="accordion-row-val">—</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customize -->
            <div class="accordion-item">
                <button class="accordion-header" onclick="toggleAccordion(this)">
                    <span>Tùy chỉnh đoạn chat</span>
                    <i class="fas fa-chevron-down accordion-chevron"></i>
                </button>
                <div class="accordion-body" style="display:none;">
                    <button class="accordion-action-btn" id="btn-panel-rename" onclick="openGroupSettings()" style="display:none;"><i class="fas fa-pen"></i> Đổi tên nhóm</button>
                    <button class="accordion-action-btn" id="btn-panel-avatar-change" onclick="openGroupSettings()" style="display:none;"><i class="fas fa-camera"></i> Đổi ảnh nhóm</button>
                    <button class="accordion-action-btn" onclick="openGroupSettings()"><i class="fas fa-cog"></i> Quản lý nhóm & biệt danh</button>
                </div>
            </div>

            <!-- Members (group only) -->
            <div class="accordion-item" id="accordion-members-item" style="display:none;">
                <button class="accordion-header" onclick="toggleAccordion(this)">
                    <span>Thành viên nhóm</span>
                    <i class="fas fa-chevron-down accordion-chevron"></i>
                </button>
                <div class="accordion-body" style="display:none;">
                    <div id="info-panel-members-list" class="info-members-list">
                        <div style="color:#94a3b8; font-size:0.82rem; padding:8px 0;">Đang tải...</div>
                    </div>
                    <button class="accordion-action-btn" onclick="addGroupMember(document.getElementById('current-conv-id').value)">
                        <i class="fas fa-user-plus"></i> Thêm thành viên
                    </button>
                </div>
            </div>

            <!-- Media -->
            <div class="accordion-item">
                <button class="accordion-header" onclick="toggleAccordion(this)">
                    <span>Media, file và link</span>
                    <i class="fas fa-chevron-down accordion-chevron"></i>
                </button>
                <div class="accordion-body" style="display:none;">
                    <div style="color:#94a3b8; font-size:0.82rem; text-align:center; padding:16px 0;">Chưa có file nào được chia sẻ.</div>
                </div>
            </div>

            <!-- Privacy -->
            <div class="accordion-item">
                <button class="accordion-header" onclick="toggleAccordion(this)">
                    <span>Quyền riêng tư & hỗ trợ</span>
                    <i class="fas fa-chevron-down accordion-chevron"></i>
                </button>
                <div class="accordion-body" style="display:none;">
                    <button class="accordion-action-btn danger-btn" id="btn-panel-leave" onclick="leaveGroup(document.getElementById('current-conv-id').value)" style="display:none;"><i class="fas fa-sign-out-alt"></i> Rời khỏi nhóm</button>
                    <button class="accordion-action-btn danger-btn" onclick="deleteCurrentConversation()"><i class="fas fa-trash-alt"></i> Xóa cuộc trò chuyện</button>
                    <button class="accordion-action-btn danger-btn"><i class="fas fa-ban"></i> Chặn người dùng</button>
                    <button class="accordion-action-btn danger-btn"><i class="fas fa-flag"></i> Báo cáo hội thoại</button>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Hidden state trackers -->
<input type="hidden" id="active-chat-user-id" value="">
<input type="hidden" id="current-conv-id" value="">
<input type="hidden" id="is-group-chat" value="0">


<script>
// Logic chính của trang tin-nhan.php
let allContacts = [];
let allGroups = [];

document.addEventListener('DOMContentLoaded', () => {
    loadContacts();

    // Lọc tìm kiếm đối tác
    const searchInput = document.getElementById('contact-search');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            filterContacts(query);
        });
    }

    // Nút quay lại danh sách đối tác trên mobile
    const backBtn = document.getElementById('btn-back-to-list');
    if (backBtn) {
        backBtn.addEventListener('click', () => {
            const container = document.querySelector('.messenger-layout');
            if (container) {
                container.classList.remove('in-chat');
            }
        });
    }
});

// Nạp danh sách liên hệ & nhóm chat từ PHP API
async function loadContacts() {
    const listContainer = document.getElementById('contacts-list-container');
    const currentUserId = document.getElementById('current-user-id').value;

    try {
        const res = await fetch(`api/api-danh-sach-user.php?user_id=${currentUserId}`);
        const data = await res.json();
        
        if (data.status === 'success') {
            allContacts = data.contacts;
            allGroups = data.groups || [];
            renderContacts(allContacts, allGroups);

            // Join các group room Socket.IO
            if (typeof joinAllMyGroupRooms === 'function') {
                joinAllMyGroupRooms();
            }

            if (typeof socket !== 'undefined' && socket.connected) {
                socket.emit('request-online-list');
            }
        } else {
            listContainer.innerHTML = `<p style="text-align:center;color:#ef4444;padding:20px;">${data.message}</p>`;
        }
    } catch (e) {
        listContainer.innerHTML = `<p style="text-align:center;color:#ef4444;padding:20px;">Lỗi kết nối máy chủ.</p>`;
    }
}

// Render danh sách lên giao diện
function renderContacts(contacts, groups = []) {
    const listContainer = document.getElementById('contacts-list-container');
    if ((!contacts || contacts.length === 0) && (!groups || groups.length === 0)) {
        listContainer.innerHTML = `<p style="text-align:center;color:#94a3b8;padding:40px 20px;font-size:0.88rem;">Không tìm thấy đối tác hoặc nhóm nào.</p>`;
        return;
    }

    let html = '';

    // 1. Render nhóm chat
    if (groups && groups.length > 0) {
        html += `<div class="sidebar-group-header" style="padding: 8px 20px; font-size: 0.72rem; font-weight: 800; color: #10b981; text-transform: uppercase; letter-spacing: 0.5px;">Nhóm trò chuyện</div>`;
        groups.forEach(g => {
            const unreadCount = parseInt(g.unread_count) || 0;
            const unreadBadge = unreadCount > 0 
                ? `<div style="background: #ef4444; color: #fff; font-size: 0.75rem; font-weight: bold; border-radius: 20px; padding: 2px 8px; margin-left: 10px;">${unreadCount} mới</div>` 
                : '';
            const isLocked = parseInt(g.is_locked) === 1;

            html += `
                <div class="contact-item group-item" data-conv-id="${g.conversation_id}" onclick="selectGroup(${g.conversation_id}, '${g.group_name.replace(/'/g, "\\'")}', '${g.group_avatar}', ${g.is_locked}, '${(g.locked_reason || '').replace(/'/g, "\\'")}')" style="display:flex; align-items:center; padding:12px 20px; cursor:pointer; border-bottom:1px solid var(--border-color, #f1f5f9); transition:all 0.15s; background: transparent;">
                    <div style="position:relative; width:44px; height:44px; flex-shrink:0;">
                        <img src="${g.group_avatar}" alt="Avatar" style="width:100%; height:100%; border-radius:12px; object-fit:cover;">
                        ${isLocked ? `<span style="position:absolute; bottom:-3px; right:-3px; background:#ef4444; color:#fff; width:18px; height:18px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:10px;"><i class="fas fa-lock"></i></span>` : ''}
                    </div>
                    <div style="margin-left:12px; flex:1; min-width:0; display:flex; align-items:center; justify-content:space-between;">
                        <div style="flex:1; min-width:0;">
                            <h4 style="font-size:0.92rem; font-weight:700; color:var(--text-color, #1e293b); margin:0; margin-bottom:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${g.group_name}</h4>
                            <span style="font-size:0.75rem; color:#94a3b8;">${g.member_count} thành viên</span>
                        </div>
                        ${unreadBadge}
                    </div>
                </div>
            `;
        });
    }

    // 2. Render liên hệ cá nhân
    if (contacts && contacts.length > 0) {
        html += `<div class="sidebar-direct-header" style="padding: 8px 20px; font-size: 0.72rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 10px;">Đối tác cá nhân</div>`;
        contacts.forEach(c => {
            const cachedIds = (window._cachedOnlineIds && window._cachedOnlineIds.length > 0) ? window._cachedOnlineIds : null;
            const isOnline = cachedIds ? cachedIds.includes(String(c.id)) : parseInt(c.is_online, 10) === 1;
            const statusClass = isOnline ? 'online' : 'offline';
            const statusText = isOnline ? 'Đang hoạt động' : 'Ngoại tuyến';
            const avatarUrl = (c.avatar.startsWith('http') || c.avatar.startsWith('data:')) ? c.avatar : `./${c.avatar}`;
            const unreadCount = parseInt(c.unread_count) || 0;
            const unreadBadge = unreadCount > 0 
                ? `<div style="background: #ef4444; color: #fff; font-size: 0.75rem; font-weight: bold; border-radius: 20px; padding: 2px 8px; margin-left: 10px;">${unreadCount} mới</div>` 
                : '';

            html += `
                <div class="contact-item direct-item" data-id="${c.id}" data-avatar="${avatarUrl.startsWith('data:') ? 'svg-auto' : avatarUrl}" onclick="selectContact(${c.id}, '${c.hoten.replace(/'/g, "\\'")}', this.dataset.avatar === 'svg-auto' ? allContacts.find(x=>x.id==${c.id})?.avatar : this.dataset.avatar)" style="display:flex; align-items:center; padding:12px 20px; cursor:pointer; border-bottom:1px solid var(--border-color, #f1f5f9); transition:all 0.15s; background: transparent;">
                    <div style="position:relative; width:44px; height:44px; flex-shrink:0;">
                        <img src="${avatarUrl}" alt="Avatar" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">
                        <span class="status-dot ${statusClass}" data-user-id="${c.id}" style="position:absolute; bottom:0; right:0; width:12px; height:12px; border-radius:50%; border:2px solid #fff; box-shadow:0 1px 3px rgba(0,0,0,0.15);"></span>
                    </div>
                    <div style="margin-left:12px; flex:1; min-width:0; display:flex; align-items:center; justify-content:space-between;">
                        <div style="flex:1; min-width:0;">
                            <h4 style="font-size:0.92rem; font-weight:700; color:var(--text-color, #1e293b); margin:0; margin-bottom:2px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${c.hoten}</h4>
                            <span class="status-text text-xs ${isOnline ? 'text-green-500' : 'text-gray-400'}" data-user-id="${c.id}" style="font-size:0.75rem; font-weight:500;">${statusText}</span>
                        </div>
                        ${unreadBadge}
                    </div>
                </div>
            `;
        });
    }

    listContainer.innerHTML = html;

    if (window._cachedOnlineIds && window._cachedOnlineIds.length > 0) {
        window.applyOnlineStatus(window._cachedOnlineIds);
    }
}

// Lọc tìm kiếm liên hệ
function filterContacts(query) {
    if (!query) {
        renderContacts(allContacts, allGroups);
        return;
    }
    const filteredContacts = allContacts.filter(c => 
        c.hoten.toLowerCase().includes(query) || 
        c.username.toLowerCase().includes(query)
    );
    const filteredGroups = allGroups.filter(g => 
        g.group_name.toLowerCase().includes(query)
    );
    renderContacts(filteredContacts, filteredGroups);
}

// Khi người dùng chọn một đối tác từ danh sách
async function selectContact(partnerId, partnerName, avatarUrl) {
    const container = document.querySelector('.messenger-layout');
    if (container) {
        container.classList.add('in-chat');
    }

    // 1. Cập nhật trạng thái Active trên danh sách liên hệ
    document.querySelectorAll('.contact-item').forEach(item => {
        item.classList.remove('active');
        if (parseInt(item.dataset.id, 10) === partnerId) {
            item.classList.add('active');
        }
    });

    // 2. Cập nhật các trường hidden
    document.getElementById('active-chat-user-id').value = partnerId;
    document.getElementById('is-group-chat').value = '0';
    
    // 3. Hiển thị UI Chatbox & Phục hồi các nút 1-1
    document.getElementById('chat-empty-state').style.display = 'none';
    document.getElementById('chat-header').style.display = 'flex';
    document.getElementById('chat-history-box').style.display = 'flex';
    document.getElementById('chat-input-area').style.display = 'flex';
    document.getElementById('btn-call-partner').style.display = 'flex';
    document.getElementById('btn-video-call').style.display = 'flex';
    document.getElementById('btn-follow-partner').style.display = 'flex';
    document.getElementById('btn-group-settings').style.display = 'none';
    document.getElementById('partner-status-dot').style.display = 'block';

    // Hiển thị info panel nếu người dùng không tắt thủ công
    const infoPanel = document.getElementById('msg-info-panel');
    const infoBtn = document.getElementById('btn-toggle-info');
    if (window.innerWidth > 900 && window.showInfoPanelState !== false) {
        infoPanel.style.display = 'flex';
        if (infoBtn) infoBtn.classList.add('active-info-btn');
    } else {
        infoPanel.style.display = 'none';
        if (infoBtn) infoBtn.classList.remove('active-info-btn');
    }

    if (typeof hideGroupLockedUI === 'function') {
        hideGroupLockedUI();
    }

    // 4. Cập nhật thông tin Header đối tác
    document.getElementById('partner-name').innerText = partnerName;
    document.getElementById('partner-avatar').src = avatarUrl;
    
    // Đồng bộ class và text online/offline từ danh sách sang header
    const partnerDot = document.querySelector(`.status-dot[data-user-id="${partnerId}"]`);
    const headerDot = document.getElementById('partner-status-dot');
    const headerText = document.getElementById('partner-status-text');
    
    headerDot.dataset.userId = partnerId;
    headerText.dataset.userId = partnerId;
    
    if (partnerDot && partnerDot.classList.contains('online')) {
        headerDot.className = 'status-dot online chat-header-status-dot';
        headerText.innerText = 'Đang hoạt động';
        headerText.className = 'chat-header-status text-green-500';
    } else {
        headerDot.className = 'status-dot offline chat-header-status-dot';
        headerText.innerText = 'Ngoại tuyến';
        headerText.className = 'chat-header-status text-gray-400';
    }

    // Cập nhật Cột phải (Info Panel)
    document.getElementById('info-panel-avatar').src = avatarUrl;
    document.getElementById('info-panel-name').innerText = partnerName;
    document.getElementById('info-panel-status').innerText = (partnerDot && partnerDot.classList.contains('online')) ? 'Đang hoạt động' : 'Ngoại tuyến';
    document.getElementById('info-panel-status-dot').className = 'status-dot info-panel-dot ' + ((partnerDot && partnerDot.classList.contains('online')) ? 'online' : 'offline');
    
    document.getElementById('info-quick-call').style.display = 'flex';
    document.getElementById('info-quick-video').style.display = 'flex';
    document.getElementById('btn-panel-rename').style.display = 'none';
    document.getElementById('btn-panel-avatar-change').style.display = 'none';
    document.getElementById('accordion-members-item').style.display = 'none';
    document.getElementById('btn-panel-leave').style.display = 'none';
    document.getElementById('info-group-name-row').style.display = 'none';

    // 5. Nạp/Tạo phòng chat để lấy conversation_id và lịch sử nhắn tin
    const chatBox = document.getElementById('chat-history-box');
    chatBox.innerHTML = `
        <div style="text-align:center;color:#94a3b8;padding:40px;">
            <i class="fas fa-spinner fa-spin" style="font-size:1.5rem;margin-bottom:10px;color:#10b981;"></i>
            <p style="font-size:0.88rem;">Đang nạp cuộc trò chuyện...</p>
        </div>
    `;

    try {
        const res = await fetch(`api/api-conversation-get-or-create.php?partner_id=${partnerId}`);
        const data = await res.json();
        
        if (data.status === 'success') {
            document.getElementById('current-conv-id').value = data.conversation_id;
            window.myGroupRole = 'owner';
            window.currentUserNickname = '';
            renderChatHistory(data.messages);
            renderPinnedMessage(data.pinned_messages);

            // Đánh dấu đã đọc & xoá badge đỏ nếu có
            const contactIndex = allContacts.findIndex(c => String(c.id) === String(partnerId));
            if (contactIndex > -1 && parseInt(allContacts[contactIndex].unread_count) > 0) {
                allContacts[contactIndex].unread_count = 0;
                const selectedEl = document.querySelector(`.contact-item[data-id="${partnerId}"]`);
                if (selectedEl) {
                    const badge = selectedEl.querySelector('div[style*="background: #ef4444"]');
                    if (badge) badge.remove();
                }

                // Cập nhật lại badge tổng trên thanh menu
                if (typeof window.updateGlobalMessageBadge === 'function') {
                    window.updateGlobalMessageBadge();
                }
                
                fetch('api/api-mark-read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        conversation_id: data.conversation_id,
                        user_id: document.getElementById('current-user-id').value
                    })
                }).catch(err => console.error("Lỗi đánh dấu đã đọc:", err));
            }
        } else {
            chatBox.innerHTML = `<p style="text-align:center;color:#ef4444;padding:20px;">Không thể tải tin nhắn.</p>`;
        }
    } catch(e) {
        chatBox.innerHTML = `<p style="text-align:center;color:#ef4444;padding:20px;">Lỗi kết nối máy chủ.</p>`;
    }

    // 6. Gán sự kiện gọi video cho nút Call
    const callBtn = document.getElementById('btn-call-partner');
    callBtn.onclick = () => {
        startVideoCall(partnerId, partnerName);
    };

    // 7. Nạp trạng thái follow của người này
    checkFollowStatus(partnerId);
}

// Khi người dùng chọn một nhóm chat từ danh sách
async function selectGroup(convId, groupName, avatarUrl, isLocked, lockedReason) {
    const container = document.querySelector('.messenger-layout');
    if (container) {
        container.classList.add('in-chat');
    }

    // 1. Cập nhật trạng thái Active trên danh sách nhóm
    document.querySelectorAll('.contact-item').forEach(item => {
        item.classList.remove('active');
        if (parseInt(item.dataset.convId, 10) === convId) {
            item.classList.add('active');
        }
    });

    // 2. Cập nhật các trường hidden
    document.getElementById('active-chat-user-id').value = '0';
    document.getElementById('current-conv-id').value = convId;
    document.getElementById('is-group-chat').value = '1';
    
    // 3. Hiển thị UI Chatbox & Ẩn các nút gọi/follow 1-1
    document.getElementById('chat-empty-state').style.display = 'none';
    document.getElementById('chat-header').style.display = 'flex';
    document.getElementById('chat-history-box').style.display = 'flex';
    document.getElementById('chat-input-area').style.display = 'flex';
    document.getElementById('btn-call-partner').style.display = 'none';
    document.getElementById('btn-video-call').style.display = 'none';
    document.getElementById('btn-follow-partner').style.display = 'none';
    document.getElementById('btn-group-settings').style.display = 'flex';
    document.getElementById('partner-status-dot').style.display = 'none';

    // Hiển thị info panel nếu người dùng không tắt thủ công
    const infoPanel = document.getElementById('msg-info-panel');
    const infoBtn = document.getElementById('btn-toggle-info');
    if (window.innerWidth > 900 && window.showInfoPanelState !== false) {
        infoPanel.style.display = 'flex';
        if (infoBtn) infoBtn.classList.add('active-info-btn');
    } else {
        infoPanel.style.display = 'none';
        if (infoBtn) infoBtn.classList.remove('active-info-btn');
    }

    // Reset UI khoá
    if (typeof hideGroupLockedUI === 'function') {
        hideGroupLockedUI();
    }

    // 4. Cập nhật thông tin Header nhóm
    document.getElementById('partner-name').innerText = groupName;
    document.getElementById('partner-avatar').src = avatarUrl;
    document.getElementById('partner-status-text').innerText = 'Nhóm trò chuyện';
    document.getElementById('partner-status-text').className = 'chat-header-status text-gray-400';

    // Cập nhật Cột phải (Info Panel)
    document.getElementById('info-panel-avatar').src = avatarUrl;
    document.getElementById('info-panel-name').innerText = groupName;
    document.getElementById('info-panel-status').innerText = 'Nhóm trò chuyện';
    document.getElementById('info-panel-status-dot').style.display = 'none';
    
    document.getElementById('info-quick-call').style.display = 'none';
    document.getElementById('info-quick-video').style.display = 'none';
    document.getElementById('btn-panel-rename').style.display = 'flex';
    document.getElementById('btn-panel-avatar-change').style.display = 'flex';
    document.getElementById('accordion-members-item').style.display = 'block';
    document.getElementById('btn-panel-leave').style.display = 'flex';
    document.getElementById('info-group-name-row').style.display = 'flex';
    document.getElementById('info-group-name-val').innerText = groupName;

    if (isLocked && typeof showGroupLockedUI === 'function') {
        showGroupLockedUI(lockedReason);
    }

    // 5. Nạp lịch sử nhắn tin nhóm
    const chatBox = document.getElementById('chat-history-box');
    chatBox.innerHTML = `
        <div style="text-align:center;color:#94a3b8;padding:40px;">
            <i class="fas fa-spinner fa-spin" style="font-size:1.5rem;margin-bottom:10px;color:#10b981;"></i>
            <p style="font-size:0.88rem;">Đang nạp cuộc trò chuyện...</p>
        </div>
    `;

    try {
        const res = await fetch(`api/api-conversation-get-or-create.php?conversation_id=${convId}`);
        const data = await res.json();
        
        if (data.status === 'success') {
            window.myGroupRole = data.my_role || 'member';
            window.currentUserNickname = data.my_nickname || '';
            renderChatHistory(data.messages);
            renderPinnedMessage(data.pinned_messages);
            if (data.members) {
                renderInfoPanelMembers(data.members);
            }

            // Đánh dấu đã đọc & xoá badge đỏ nếu có
            const groupIndex = allGroups.findIndex(g => String(g.conversation_id) === String(convId));
            if (groupIndex > -1 && parseInt(allGroups[groupIndex].unread_count) > 0) {
                allGroups[groupIndex].unread_count = 0;
                const selectedEl = document.querySelector(`.contact-item[data-conv-id="${convId}"]`);
                if (selectedEl) {
                    const badge = selectedEl.querySelector('div[style*="background: #ef4444"]');
                    if (badge) badge.remove();
                }

                // Cập nhật lại badge tổng trên thanh menu
                if (typeof window.updateGlobalMessageBadge === 'function') {
                    window.updateGlobalMessageBadge();
                }
                
                fetch('api/api-mark-read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        conversation_id: convId,
                        user_id: document.getElementById('current-user-id').value
                    })
                }).catch(err => console.error("Lỗi đánh dấu đã đọc:", err));
            }
        } else {
            chatBox.innerHTML = `<p style="text-align:center;color:#ef4444;padding:20px;">Không thể tải tin nhắn.</p>`;
        }
    } catch(e) {
        chatBox.innerHTML = `<p style="text-align:center;color:#ef4444;padding:20px;">Lỗi kết nối máy chủ.</p>`;
    }
}

// Render lịch sử chat vào khung tin nhắn
function renderChatHistory(messages) {
    const chatBox = document.getElementById('chat-history-box');
    const currentUserId = parseInt(document.getElementById('current-user-id').value, 10);
    
    // Nạp danh sách tin nhắn bị xóa cục bộ và thành viên bị chặn
    const deletedKey = `deleted_msg_ids_${currentUserId}`;
    const blockedKey = `blocked_user_ids_${currentUserId}`;
    const deletedIds = JSON.parse(localStorage.getItem(deletedKey) || '[]').map(String);
    const blockedUserIds = JSON.parse(localStorage.getItem(blockedKey) || '[]').map(String);

    if (!messages || messages.length === 0) {
        chatBox.innerHTML = `<div style="text-align:center;color:#94a3b8;padding:40px;font-size:0.88rem;">Chưa có tin nhắn nào. Hãy gửi lời chào đầu tiên! 👋</div>`;
        return;
    }

    chatBox.innerHTML = '';
    messages.forEach(msg => {
        // Bỏ qua tin bị xóa cục bộ hoặc của người dùng bị chặn
        if (deletedIds.includes(String(msg.id))) return;
        if (blockedUserIds.includes(String(msg.sender_id))) return;

        const direction = parseInt(msg.sender_id, 10) === currentUserId ? 'sent' : 'received';
        const msgType = msg.type || 'text';
        appendRealtimeMessageToUI(msg.sender_id, msg.content, direction, msg.sender_name, msg.id, msgType, false);
    });

    // Scroll to bottom after rendering history
    requestAnimationFrame(() => {
        chatBox.scrollTop = chatBox.scrollHeight;
    });
}

// Mở modal tạo nhóm chat (SweetAlert2 custom)
function openCreateGroupModal() {
    if (!allContacts || allContacts.length === 0) {
        Swal.fire('Lỗi', 'Không tìm thấy đối tác nào để tạo nhóm.', 'error');
        return;
    }

    let userCheckboxes = allContacts.map(c => {
        const avatarUrl = (c.avatar.startsWith('http') || c.avatar.startsWith('data:')) ? c.avatar : `./${c.avatar}`;
        return `
        <label style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-bottom:1px solid #f1f5f9; cursor:pointer; font-size:0.9rem; text-align:left; transition:background 0.2s;">
            <input type="checkbox" name="group-members" value="${c.id}" style="width:18px; height:18px; accent-color:#10b981; cursor:pointer;">
            <img src="${avatarUrl}" style="width:30px; height:30px; border-radius:50%; object-fit:cover;">
            <span style="font-weight:600; color:#1e293b;">${c.hoten}</span>
        </label>
        `;
    }).join('');

    Swal.fire({
        title: 'Tạo Nhóm Trò Chuyện',
        html: `
            <div style="margin-bottom:18px; text-align:left;">
                <label style="font-weight:700; font-size:0.82rem; color:#64748b; display:block; margin-bottom:6px; text-transform:uppercase;">Tên nhóm:</label>
                <input type="text" id="swal-group-name" class="swal2-input" placeholder="Nhập tên nhóm..." style="width:100%; margin:0; height:44px; border-radius:8px; font-size:0.9rem; border:1px solid #cbd5e1; padding:0 12px; box-sizing:border-box;">
            </div>
            <div style="text-align:left;">
                <label style="font-weight:700; font-size:0.82rem; color:#64748b; display:block; margin-bottom:6px; text-transform:uppercase;">Chọn thành viên:</label>
                <div style="max-height:220px; overflow-y:auto; border:1px solid #cbd5e1; border-radius:8px; padding:4px; background:#fff;">
                    ${userCheckboxes}
                </div>
            </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Tạo Nhóm',
        cancelButtonText: 'Huỷ',
        confirmButtonColor: '#10b981',
        preConfirm: () => {
            const name = document.getElementById('swal-group-name').value.trim();
            const checkedBoxes = document.querySelectorAll('input[name="group-members"]:checked');
            const memberIds = Array.from(checkedBoxes).map(cb => parseInt(cb.value));

            if (!name) {
                Swal.showValidationMessage('Vui lòng nhập tên nhóm.');
                return false;
            }
            if (memberIds.length < 1) {
                Swal.showValidationMessage('Vui lòng chọn ít nhất 1 đối tác.');
                return false;
            }

            return { name, members: memberIds };
        }
    }).then(async (result) => {
        if (result.isConfirmed) {
            const { name, members } = result.value;
            try {
                const res = await fetch('api/group-chat.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'create_group', name, members })
                });
                const d = await res.json();
                if (d.success) {
                    Swal.fire({ icon: 'success', title: 'Thành công', text: d.message, confirmButtonColor: '#10b981' });
                    // Tải lại danh bạ & tự chọn nhóm mới tạo
                    await loadContacts();
                    selectGroup(d.conversation_id, d.group_name, 'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCAxMDAgMTAwIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjMTBiOTgxIiByeD0iMjAiLz48dGV4dCB4PSI1MCUiIHk9IjU0JSIgZG9taW5hbnQtYmFzZWxpbmU9Im1pZGRsZSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZm9udC1zaXplPSIzNiIgZm9udC1mYW1pbHk9InNhbnMtc2VyaWYiIGZvbnQtd2VpZ2h0PSJib2xkIiBmaWxsPSIjZmZmZmZmIj7wn5SuperPC90ZXh0Pjwvc3ZnPg==', 0, '');
                } else {
                    Swal.fire('Lỗi', d.message, 'error');
                }
            } catch(e) {
                Swal.fire('Lỗi', 'Không thể kết nối máy chủ để tạo nhóm.', 'error');
            }
        }
    });
}

// Kiểm tra và hiển thị nút follow/unfollow
async function checkFollowStatus(partnerId) {
    const followBtn = document.getElementById('btn-follow-partner');
    try {
        followBtn.onclick = async () => {
            const res = await fetch('api/follow.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ following_id: partnerId, action: 'toggle' })
            });
            const data = await res.json();
            if (data.status === 'success') {
                updateFollowButtonUI(data.is_following);
            } else {
                alert(data.message);
            }
        };
    } catch(e) {}
}

function updateFollowButtonUI(isFollowing) {
    const followBtn = document.getElementById('btn-follow-partner');
    if (isFollowing) {
        followBtn.innerHTML = `<i class="fas fa-check"></i> Đang theo dõi`;
        followBtn.style.background = '#e2e8f0';
        followBtn.style.color = '#475569';
        followBtn.style.borderColor = '#cbd5e1';
    } else {
        followBtn.innerHTML = `<i class="fas fa-plus"></i> Theo dõi`;
        followBtn.style.background = 'transparent';
        followBtn.style.color = '#10b981';
        followBtn.style.borderColor = '#10b981';
    }
}

// ===== CÁC HÀM QUẢN LÝ NHÓM (ZALO / MESSENGER STYLE) =====

async function editMemberNickname(convId, userId, currentNickname, memberName) {
    const { value: newNickname } = await Swal.fire({
        title: 'Đặt biệt danh',
        text: `Nhập biệt danh cho ${memberName}:`,
        input: 'text',
        inputValue: currentNickname,
        showCancelButton: true,
        confirmButtonText: 'Lưu',
        cancelButtonText: 'Huỷ',
        confirmButtonColor: '#10b981'
    });

    if (newNickname !== undefined) {
        try {
            const res = await fetch('api/group-chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'set_nickname',
                    conversation_id: convId,
                    user_id: userId,
                    nickname: newNickname.trim()
                })
            });
            const data = await res.json();
            if (data.success) {
                loadContacts();
                if (typeof socket !== 'undefined' && socket.connected) {
                    socket.emit('send-message', {
                        conversationId: convId,
                        senderId: parseInt(document.getElementById('current-user-id').value, 10),
                        messageContent: `✏️ Biệt danh thành viên đã được cập nhật.`,
                        isGroup: true,
                        senderName: 'Hệ thống'
                    });
                }
                openGroupSettings();
                Swal.fire({ icon: 'success', title: 'Thành công', text: data.message, timer: 1500, showConfirmButton: false });
            } else {
                Swal.fire('Lỗi', data.message, 'error');
            }
        } catch (e) {
            Swal.fire('Lỗi', 'Không thể kết nối máy chủ.', 'error');
        }
    }
}

async function openGroupSettings() {
    const convId = document.getElementById('current-conv-id').value;
    if (!convId) return;

    try {
        const res = await fetch(`api/group-chat.php?action=get_group_info&conversation_id=${convId}`);
        const data = await res.json();
        if (!data.success) {
            Swal.fire('Lỗi', data.message, 'error');
            return;
        }

        const group = data.group;
        const members = data.members;
        const myRole = data.my_role;
        const currentUserId = parseInt(document.getElementById('current-user-id').value, 10);

        // Build HTML danh sách thành viên
        let membersHtml = '';
        members.forEach(m => {
            const isMe = parseInt(m.id, 10) === currentUserId;
            const roleBadge = m.role === 'owner' 
                ? '<span style="background:linear-gradient(135deg,#f59e0b,#d97706); color:#fff; font-size:0.62rem; padding:2px 7px; border-radius:10px; font-weight:700; letter-spacing:0.3px;">👑 Trưởng nhóm</span>' 
                : m.role === 'admin' 
                    ? '<span style="background:linear-gradient(135deg,#3b82f6,#2563eb); color:#fff; font-size:0.62rem; padding:2px 7px; border-radius:10px; font-weight:700; letter-spacing:0.3px;">⭐ Phó nhóm</span>' 
                    : '<span style="background:#e2e8f0; color:#64748b; font-size:0.62rem; padding:2px 7px; border-radius:10px; font-weight:600;">Thành viên</span>';
            
            let kickBtn = '';
            if (!isMe && (myRole === 'owner' || (myRole === 'admin' && m.role === 'member'))) {
                kickBtn = `<button onclick="removeGroupMember(${convId}, ${m.id}, '${m.hoten.replace(/'/g, "\\'")}')" style="display:inline-flex;align-items:center;gap:5px;background:rgba(239,68,68,0.1);color:#ef4444;border:1px solid rgba(239,68,68,0.3);border-radius:8px;padding:5px 10px;font-size:0.78rem;font-weight:600;cursor:pointer;white-space:nowrap;" onmouseover="this.style.background='rgba(239,68,68,0.2)'" onmouseout="this.style.background='rgba(239,68,68,0.1)'" title="Kick khỏi nhóm"><i class="fas fa-user-times"></i> Kick</button>`;
            }

            membersHtml += `
                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid var(--border-color,rgba(0,0,0,0.06));">
                    <div style="display:flex;align-items:center;gap:10px;min-width:0;flex:1;">
                        <img src="${m.avatar}" alt="Avatar" style="width:38px;height:38px;border-radius:50%;object-fit:cover;flex-shrink:0;border:2px solid ${m.role==='owner'?'#f59e0b':m.role==='admin'?'#3b82f6':'#e2e8f0'};">
                        <div style="text-align:left;min-width:0;flex:1;">
                            <div style="font-weight:600;font-size:0.88rem;color:var(--text-color,#1f2937);display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
                                <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:150px;" title="${m.hoten}">
                                    ${m.nickname ? `${m.nickname} <span style="color:#64748b;font-weight:400;font-size:0.78rem;">(${m.hoten})</span>` : m.hoten}
                                    ${isMe ? '<span style="color:#10b981;font-size:0.75rem;font-weight:500;">(Bạn)</span>' : ''}
                                </span>
                            </div>
                            <div style="margin-top:4px;">${roleBadge}</div>
                        </div>
                    </div>
                    <div style="flex-shrink:0;margin-left:8px;">${kickBtn}</div>
                </div>
            `;
        });

        // HTML cho panel Quản lý
        const modalHtml = `
            <div style="font-family:inherit; color:var(--text-color, #1f2937);">
                <!-- Avatar & Hover Upload -->
                <div style="position:relative; width:100px; height:100px; margin:0 auto 16px; border-radius:50%; overflow:hidden; border:3px solid #10b981; cursor:pointer;" onclick="document.getElementById('group-avatar-file-input').click()">
                    <img id="modal-group-avatar" src="${group.group_avatar}" alt="Group Avatar" style="width:100%; height:100%; object-fit:cover;">
                    <div style="position:absolute; inset:0; background:rgba(0,0,0,0.6); display:flex; align-items:center; justify-content:center; color:#fff; font-size:0.75rem; font-weight:700; opacity:0; transition:opacity 0.2s;" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0">
                        <i class="fas fa-camera" style="margin-right:4px;"></i> Đổi ảnh
                    </div>
                </div>
                <input type="file" id="group-avatar-file-input" style="display:none;" accept="image/*" onchange="uploadGroupAvatar(${convId}, this)">

                <!-- Tên nhóm -->
                <div style="display:flex; align-items:center; justify-content:center; gap:8px; margin-bottom:20px;">
                    <h3 id="modal-group-name" style="font-size:1.15rem; font-weight:800; margin:0;">${group.group_name}</h3>
                    ${(myRole === 'owner' || myRole === 'admin') ? `<button onclick="renameGroup(${convId}, '${group.group_name.replace(/'/g, "\\'")}')" style="background:none; border:none; color:#10b981; cursor:pointer; font-size:0.9rem;" title="Đổi tên nhóm"><i class="fas fa-pen"></i></button>` : ''}
                </div>

                <!-- Thao tác nhanh -->
                <div style="display:flex; justify-content:center; gap:10px; margin-bottom:24px;">
                    <button onclick="addGroupMember(${convId})" style="background:#10b981; color:#fff; border:none; padding:8px 16px; border-radius:20px; font-weight:600; font-size:0.8rem; cursor:pointer; display:flex; align-items:center; gap:6px;">
                        <i class="fas fa-user-plus"></i> Thêm thành viên
                    </button>
                    <button onclick="leaveGroup(${convId})" style="background:rgba(239,68,68,0.1); color:#ef4444; border:1px solid rgba(239,68,68,0.2); padding:8px 16px; border-radius:20px; font-weight:600; font-size:0.8rem; cursor:pointer; display:flex; align-items:center; gap:6px;">
                        <i class="fas fa-sign-out-alt"></i> Rời nhóm
                    </button>
                </div>

                <!-- Danh sách thành viên -->
                <div style="text-align:left; max-height:220px; overflow-y:auto; padding-right:4px;">
                    <h4 style="font-size:0.85rem; font-weight:700; color:#64748b; margin-bottom:10px; text-transform:uppercase; letter-spacing:0.5px;">Thành viên nhóm (${members.length})</h4>
                    <div style="display:flex; flex-direction:column; gap:4px;">
                        ${membersHtml}
                    </div>
                </div>
            </div>
        `;

        Swal.fire({
            title: 'Quản lý nhóm',
            html: modalHtml,
            showConfirmButton: false,
            showCloseButton: true,
            width: '420px',
            scrollbarPadding: false
        });

    } catch (e) {
        Swal.fire('Lỗi', 'Không thể kết nối máy chủ để lấy thông tin nhóm.', 'error');
    }
}

// Thay đổi avatar nhóm
async function uploadGroupAvatar(convId, input) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];

    const fd = new FormData();
    fd.append('action', 'change_avatar');
    fd.append('conversation_id', convId);
    fd.append('avatar', file);

    Swal.fire({
        title: 'Đang tải lên...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    try {
        const res = await fetch('api/group-chat.php', {
            method: 'POST',
            body: fd
        });
        const data = await res.json();
        if (data.success) {
            // Cập nhật giao diện modal và giao diện chat chính
            const imgEl = document.getElementById('modal-group-avatar');
            if (imgEl) imgEl.src = data.avatar_url;
            
            const headerAvatar = document.getElementById('partner-avatar');
            if (headerAvatar) headerAvatar.src = data.avatar_url;

            // Cập nhật lại trong sidebar list
            const sidebarItem = document.querySelector(`.contact-item[data-conv-id="${convId}"] img`);
            if (sidebarItem) sidebarItem.src = data.avatar_url;

            // Cập nhật cache allGroups
            const groupIndex = allGroups.findIndex(g => String(g.conversation_id) === String(convId));
            if (groupIndex > -1) {
                allGroups[groupIndex].group_avatar = data.avatar_url;
            }

            // Tải lại tin nhắn hệ thống realtime
            const chatBox = document.getElementById('chat-history-box');
            if (chatBox) {
                const sysMsgHtml = `
                    <div style="display:flex; justify-content:center; margin-bottom:15px; width:100%;">
                        <div style="background:#e2e8f0; color:#475569; padding:6px 16px; border-radius:20px; font-size:0.78rem; font-weight:600; box-shadow:0 1px 2px rgba(0,0,0,0.05); display:flex; align-items:center; gap:6px;">
                            📷 Đang cập nhật ảnh đại diện nhóm...
                        </div>
                    </div>
                `;
                chatBox.insertAdjacentHTML('beforeend', sysMsgHtml);
                chatBox.scrollTop = chatBox.scrollHeight;
            }

            // Đồng bộ thông qua Socket.io nếu có
            if (typeof socket !== 'undefined' && socket.connected) {
                socket.emit('send-message', {
                    conversationId: convId,
                    senderId: parseInt(document.getElementById('current-user-id').value, 10),
                    messageContent: '📷 Đã cập nhật ảnh đại diện nhóm.',
                    isGroup: true,
                    senderName: 'Hệ thống'
                });
            }

            // Tự động tải lại thông tin quản lý để đồng bộ thành viên
            setTimeout(() => {
                openGroupSettings();
            }, 1000);
            
            Swal.fire({ icon: 'success', title: 'Thành công', text: 'Đã cập nhật ảnh đại diện nhóm.', timer: 1500, showConfirmButton: false });
        } else {
            Swal.fire('Lỗi', data.message, 'error');
        }
    } catch (e) {
        Swal.fire('Lỗi', 'Không thể kết nối máy chủ.', 'error');
    }
}

// Đổi tên nhóm
async function renameGroup(convId, currentName) {
    const { value: newName } = await Swal.fire({
        title: 'Đổi tên nhóm',
        input: 'text',
        inputValue: currentName,
        showCancelButton: true,
        confirmButtonText: 'Đổi tên',
        cancelButtonText: 'Huỷ',
        confirmButtonColor: '#10b981',
        preConfirm: (value) => {
            if (!value.trim()) {
                Swal.showValidationMessage('Tên nhóm không được để trống.');
                return false;
            }
            return value.trim();
        }
    });

    if (newName) {
        const fd = new FormData();
        fd.append('action', 'rename_group');
        fd.append('conversation_id', convId);
        fd.append('name', newName);

        try {
            const res = await fetch('api/group-chat.php', {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            if (data.success) {
                document.getElementById('partner-name').innerText = newName;
                const sidebarName = document.querySelector(`.contact-item[data-conv-id="${convId}"] h4`);
                if (sidebarName) sidebarName.innerText = newName;

                // Cập nhật cache allGroups
                const groupIndex = allGroups.findIndex(g => String(g.conversation_id) === String(convId));
                if (groupIndex > -1) {
                    allGroups[groupIndex].group_name = newName;
                }

                // Gửi Socket update
                if (typeof socket !== 'undefined' && socket.connected) {
                    socket.emit('send-message', {
                        conversationId: convId,
                        senderId: parseInt(document.getElementById('current-user-id').value, 10),
                        messageContent: `🎉 Tên nhóm đã được đổi thành "${newName}".`,
                        isGroup: true,
                        senderName: 'Hệ thống'
                    });
                }

                openGroupSettings();
                Swal.fire({ icon: 'success', title: 'Thành công', text: 'Đã đổi tên nhóm.', timer: 1500, showConfirmButton: false });
            } else {
                Swal.fire('Lỗi', data.message, 'error');
            }
        } catch (e) {
            Swal.fire('Lỗi', 'Không thể kết nối máy chủ.', 'error');
        }
    }
}

// Xoá thành viên khỏi nhóm
async function removeGroupMember(convId, memberId, memberName) {
    const confirm = await Swal.fire({
        title: 'Xoá thành viên?',
        text: `Bạn có chắc chắn muốn xoá ${memberName} khỏi nhóm không?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Xoá',
        cancelButtonText: 'Huỷ',
        confirmButtonColor: '#ef4444'
    });

    if (confirm.isConfirmed) {
        const fd = new FormData();
        fd.append('action', 'remove_member');
        fd.append('conversation_id', convId);
        fd.append('member_id', memberId);

        try {
            const res = await fetch('api/group-chat.php', {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            if (data.success) {
                // Tải lại modal quản lý nhóm
                openGroupSettings();
                Swal.fire({ icon: 'success', title: 'Đã xoá', text: data.message, timer: 1500, showConfirmButton: false });
            } else {
                Swal.fire('Lỗi', data.message, 'error');
            }
        } catch (e) {
            Swal.fire('Lỗi', 'Không thể kết nối máy chủ.', 'error');
        }
    }
}

// Thêm thành viên mới
async function addGroupMember(convId) {
    // 1. Lấy danh sách thành viên hiện tại của nhóm
    let currentMemberIds = [];
    try {
        const infoRes = await fetch(`api/group-chat.php?action=get_group_info&conversation_id=${convId}`);
        const infoData = await infoRes.json();
        if (infoData.success) {
            currentMemberIds = infoData.members.map(m => parseInt(m.id, 10));
        }
    } catch(e) {}

    // 2. Tìm các đối tác cá nhân chưa có trong nhóm
    const availableContacts = allContacts.filter(c => !currentMemberIds.includes(parseInt(c.id, 10)));
    if (availableContacts.length === 0) {
        Swal.fire('Thông báo', 'Tất cả đối tác trong danh bạ của bạn đã tham gia nhóm này rồi.', 'info');
        return;
    }

    // Build checkbox list
    let checkboxHtml = '';
    availableContacts.forEach(c => {
        checkboxHtml += `
            <label style="display:flex; align-items:center; gap:10px; padding:10px 0; cursor:pointer; text-align:left; font-size:0.92rem; border-bottom:1px solid rgba(0,0,0,0.05); color:var(--text-color, #1f2937);">
                <input type="checkbox" name="new-member-checkbox" value="${c.id}" style="width:18px; height:18px; accent-color:#10b981;">
                <img src="${c.avatar.startsWith('http') ? c.avatar : './' + c.avatar}" alt="Avatar" style="width:30px; height:30px; border-radius:50%; object-fit:cover;">
                <span style="font-weight:600;">${c.hoten}</span>
            </label>
        `;
    });

    const { value: formValues } = await Swal.fire({
        title: 'Thêm thành viên',
        html: `
            <div style="max-height:280px; overflow-y:auto; padding-right:6px; font-family:inherit;">
                ${checkboxHtml}
            </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: 'Thêm vào nhóm',
        cancelButtonText: 'Huỷ',
        confirmButtonColor: '#10b981',
        preConfirm: () => {
            const checked = Array.from(document.querySelectorAll('input[name="new-member-checkbox"]:checked')).map(el => parseInt(el.value, 10));
            if (checked.length === 0) {
                Swal.showValidationMessage('Vui lòng chọn ít nhất 1 thành viên.');
                return false;
            }
            return checked;
        }
    });

    if (formValues && formValues.length > 0) {
        const fd = new FormData();
        fd.append('action', 'add_member');
        fd.append('conversation_id', convId);
        formValues.forEach(id => fd.append('member_ids[]', id));

        try {
            const res = await fetch('api/group-chat.php', {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            if (data.success) {
                openGroupSettings();
                Swal.fire({ icon: 'success', title: 'Thêm thành công', text: data.message, timer: 1500, showConfirmButton: false });
            } else {
                Swal.fire('Lỗi', data.message, 'error');
            }
        } catch (e) {
            Swal.fire('Lỗi', 'Không thể kết nối máy chủ.', 'error');
        }
    }
}

// Rời nhóm
async function leaveGroup(convId) {
    const confirm = await Swal.fire({
        title: 'Rời khỏi nhóm?',
        text: 'Bạn sẽ không thể nhận hay gửi tin nhắn trong nhóm này nữa!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Rời nhóm',
        cancelButtonText: 'Huỷ',
        confirmButtonColor: '#ef4444'
    });

    if (confirm.isConfirmed) {
        const fd = new FormData();
        fd.append('action', 'leave_group');
        fd.append('conversation_id', convId);

        try {
            const res = await fetch('api/group-chat.php', {
                method: 'POST',
                body: fd
            });
            const data = await res.json();
            if (data.success) {
                // Đóng hộp thoại settings
                Swal.close();

                // Reload lại danh sách đối tác
                loadContacts();

                // Đưa chat panel về Empty State
                document.getElementById('chat-empty-state').style.display = 'flex';
                document.getElementById('chat-header').style.display = 'none';
                document.getElementById('chat-history-box').style.display = 'none';
                document.getElementById('chat-input-area').style.display = 'none';
                document.getElementById('msg-info-panel').style.display = 'none';

                Swal.fire({ icon: 'success', title: 'Đã rời nhóm', text: 'Bạn đã rời nhóm thành công.', timer: 2000, showConfirmButton: false });
            } else {
                Swal.fire('Lỗi', data.message, 'error');
            }
        } catch (e) {
            Swal.fire('Lỗi', 'Không thể kết nối máy chủ.', 'error');
        }
    }
}

// ════ UI INTERACTION FUNCTIONS ════

// Biến lưu trạng thái panel của người dùng (mặc định mở trên desktop nếu chưa được lưu)
const savedPanelState = localStorage.getItem('showInfoPanelState');
window.showInfoPanelState = savedPanelState !== null ? (savedPanelState === 'true') : (window.innerWidth > 900);

function toggleInfoPanel() {
    const panel = document.getElementById('msg-info-panel');
    const toggleBtn = document.getElementById('btn-toggle-info');
    if (panel.style.display === 'none' || !panel.style.display) {
        panel.style.display = 'flex';
        if (toggleBtn) toggleBtn.classList.add('active-info-btn');
        window.showInfoPanelState = true;
        localStorage.setItem('showInfoPanelState', 'true');
    } else {
        panel.style.display = 'none';
        if (toggleBtn) toggleBtn.classList.remove('active-info-btn');
        window.showInfoPanelState = false;
        localStorage.setItem('showInfoPanelState', 'false');
    }
}


function toggleAccordion(btn) {
    const body = btn.nextElementSibling;
    const chevron = btn.querySelector('.accordion-chevron');
    if (body.style.display === 'none' || !body.style.display) {
        body.style.display = 'block';
        btn.classList.add('open');
        chevron.style.transform = 'rotate(180deg)';
    } else {
        body.style.display = 'none';
        btn.classList.remove('open');
        chevron.style.transform = 'rotate(0deg)';
    }
}

function switchTab(tabId, btnElement) {
    // Xóa active khỏi tất cả tabs
    document.querySelectorAll('.sidebar-tab').forEach(t => t.classList.remove('active'));
    btnElement.classList.add('active');

    // Lọc danh sách dựa trên tabId
    document.querySelectorAll('.contact-item').forEach(item => {
        if (tabId === 'all') {
            item.style.display = 'flex';
        } else if (tabId === 'groups') {
            if (item.classList.contains('group-item')) item.style.display = 'flex';
            else item.style.display = 'none';
        } else if (tabId === 'unread') {
            const hasUnread = item.querySelector('div[style*="background: #ef4444"]');
            if (hasUnread) item.style.display = 'flex';
            else item.style.display = 'none';
        }
    });

    // Hiện/Ẩn tiêu đề nhóm hoặc đối tác cá nhân
    const groupHeader = document.querySelector('.sidebar-group-header');
    const directHeader = document.querySelector('.sidebar-direct-header');
    
    if (tabId === 'all') {
        if (groupHeader) groupHeader.style.display = 'block';
        if (directHeader) directHeader.style.display = 'block';
    } else if (tabId === 'groups') {
        if (groupHeader) groupHeader.style.display = 'none'; // Không cần tiêu đề "Nhóm trò chuyện" vì toàn nhóm
        if (directHeader) directHeader.style.display = 'none';
    } else if (tabId === 'unread') {
        if (groupHeader) groupHeader.style.display = 'none';
        if (directHeader) directHeader.style.display = 'none';
    }
}

function sendLike() {
    const input = document.getElementById('message-input');
    input.value = '👍';
    sendRealtimeMessage();
}

function showSidebarMenu(btn) {
    Swal.fire({
        title: 'Tùy chọn',
        html: `
            <button onclick="Swal.close();" style="width:100%; padding:12px; background:#f0f2f5; border:none; border-radius:10px; font-weight:600; margin-bottom:8px; cursor:pointer;"><i class="fas fa-check-double"></i> Đánh dấu tất cả đã đọc</button>
            <button onclick="Swal.close();" style="width:100%; padding:12px; background:#f0f2f5; border:none; border-radius:10px; font-weight:600; margin-bottom:8px; cursor:pointer;"><i class="fas fa-cog"></i> Cài đặt</button>
        `,
        showConfirmButton: false,
        showCloseButton: true
    });
}

// Hàm render lại danh sách thành viên cho Info Panel
function renderInfoPanelMembers(members) {
    const listEl = document.getElementById('info-panel-members-list');
    if (!listEl) return;
    let html = '';
    const currentUserId = parseInt(document.getElementById('current-user-id').value, 10);
    
    members.forEach(m => {
        const isMe = parseInt(m.id, 10) === currentUserId;
        const roleClass = m.role === 'owner' ? 'role-owner' : (m.role === 'admin' ? 'role-admin' : '');
        const roleLabel = m.role === 'owner' ? 'Trưởng nhóm' : (m.role === 'admin' ? 'Phó nhóm' : '');
        const displayName = m.nickname ? `${m.nickname} (${m.hoten})` : m.hoten;
        
        html += `
            <div class="info-member-row">
                <img src="${m.avatar}" alt="Avatar" class="info-member-avatar">
                <div class="info-member-name" title="${m.hoten}">${displayName} ${isMe ? '(Bạn)' : ''}</div>
                ${roleLabel ? `<div class="info-member-role ${roleClass}">${roleLabel}</div>` : ''}
            </div>
        `;
    });
    listEl.innerHTML = html;
}

async function deleteCurrentConversation() {
    const convId = document.getElementById('current-conv-id')?.value;
    if (!convId) return;

    const result = await Swal.fire({
        title: 'Xóa cuộc trò chuyện?',
        text: 'Lịch sử tin nhắn của cuộc trò chuyện này sẽ bị ẩn khỏi giao diện của bạn. Khi có tin nhắn mới, cuộc trò chuyện sẽ tự động xuất hiện lại.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Xóa',
        cancelButtonText: 'Hủy'
    });

    if (result.isConfirmed) {
        Swal.fire({
            title: 'Đang xóa...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        try {
            const res = await fetch('api/delete-conversation.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ conversation_id: parseInt(convId, 10) })
            });
            const data = await res.json();
            Swal.close();

            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Đã xóa',
                    text: 'Cuộc trò chuyện đã được ẩn thành công.',
                    timer: 1500,
                    showConfirmButton: false
                });

                // Tải lại danh bạ
                loadContacts();

                // Đưa chat panel về Empty State
                document.getElementById('chat-empty-state').style.display = 'flex';
                document.getElementById('chat-header').style.display = 'none';
                document.getElementById('chat-history-box').style.display = 'none';
                document.getElementById('chat-input-area').style.display = 'none';
                document.getElementById('msg-info-panel').style.display = 'none';
            } else {
                Swal.fire('Lỗi', data.message, 'error');
            }
        } catch (e) {
            Swal.close();
            Swal.fire('Lỗi', 'Không thể kết nối máy chủ.', 'error');
        }
    }
}

// ════ EMOJI / FILE UPLOAD ════

function toggleEmojiPickerPanel() {
    const panel = document.getElementById('emoji-picker-panel');
    if (!panel) return;
    panel.style.display = panel.style.display === 'none' || !panel.style.display ? 'block' : 'none';
}

function insertEmoji(emoji) {
    const input = document.getElementById('message-input');
    if (!input) return;
    const pos = input.selectionStart || input.value.length;
    input.value = input.value.slice(0, pos) + emoji + input.value.slice(pos);
    input.focus();
    // Đóng panel sau khi chèn emoji
    const panel = document.getElementById('emoji-picker-panel');
    if (panel) panel.style.display = 'none';
}

// Đóng emoji picker khi click bên ngoài
document.addEventListener('click', (e) => {
    const panel = document.getElementById('emoji-picker-panel');
    if (!panel || panel.style.display === 'none') return;
    const emojiBtn = e.target.closest('.msg-input-action-btn');
    if (!e.target.closest('#emoji-picker-panel') && !emojiBtn) {
        panel.style.display = 'none';
    }
});

async function handleChatFileUpload(input, type) {
    if (!input.files || !input.files[0]) return;
    const file = input.files[0];

    // Kiểm tra file chưa chọn conv
    const convId = document.getElementById('current-conv-id')?.value;
    if (!convId) {
        Swal.fire('Lỗi', 'Vui lòng chọn một cuộc trò chuyện trước khi gửi tệp.', 'error');
        return;
    }

    // Hiện loading
    Swal.fire({
        title: type === 'image' ? 'Đang tải ảnh lên...' : 'Đang tải tệp lên...',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    try {
        const fd = new FormData();
        fd.append('file', file);
        
        const res = await fetch('api/chat-upload.php', { method: 'POST', body: fd });
        const data = await res.json();
        
        Swal.close();
        
        if (data.success) {
            sendRealtimeMediaMessage(data.url, data.type);
        } else {
            Swal.fire('Lỗi', data.message || 'Tải tệp thất bại', 'error');
        }
    } catch (e) {
        Swal.close();
        Swal.fire('Lỗi', 'Không thể kết nối máy chủ.', 'error');
    }

    // Reset input để có thể chọn lại cùng file
    input.value = '';
}

// ════ DYNAMIC CONTEXT MENU & LOCAL BLOCK/DELETE HELPERS ════
document.addEventListener('DOMContentLoaded', () => {
    // Tự động tiêm phần tử menu ngữ cảnh vào body
    let menu = document.getElementById('msg-context-menu');
    if (!menu) {
        menu = document.createElement('div');
        menu.id = 'msg-context-menu';
        menu.className = 'msg-context-menu';
        document.body.appendChild(menu);
    }
});

// Đóng menu khi click bên ngoài
document.addEventListener('mousedown', (e) => {
    const menu = document.getElementById('msg-context-menu');
    if (menu && menu.style.display !== 'none' && !menu.contains(e.target)) {
        menu.style.display = 'none';
    }
});

function deleteMessageLocally(messageId) {
    const currentUserId = document.getElementById('current-user-id').value;
    const key = `deleted_msg_ids_${currentUserId}`;
    let deletedIds = JSON.parse(localStorage.getItem(key) || '[]');
    if (!deletedIds.includes(String(messageId))) {
        deletedIds.push(String(messageId));
        localStorage.setItem(key, JSON.stringify(deletedIds));
    }
    const msgRow = document.getElementById('msg-item-' + messageId);
    if (msgRow) {
        msgRow.remove();
    }
    Swal.fire({ icon: 'success', title: 'Đã xóa tin nhắn cục bộ', toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 });
}

function openMemberContextMenu(e, memberId, memberName, memberRole) {
    e.preventDefault();
    e.stopPropagation();
    const menu = document.getElementById('msg-context-menu');
    if (!menu) return;

    menu.innerHTML = '';
    const currentUserId = parseInt(document.getElementById('current-user-id').value, 10);
    const isMe = parseInt(memberId, 10) === currentUserId;
    
    // Tiêu đề hiển thị tên thành viên
    const titleItem = document.createElement('div');
    titleItem.style.cssText = 'font-weight:700; font-size:0.75rem; text-transform:uppercase; color:#64748b; padding:6px 12px; border-bottom:1px solid rgba(0,0,0,0.05); margin-bottom:4px;';
    titleItem.innerText = memberName;
    menu.appendChild(titleItem);

    if (!isMe) {
        // Chặn / Bỏ chặn
        const blockedKey = `blocked_user_ids_${currentUserId}`;
        let blockedUserIds = JSON.parse(localStorage.getItem(blockedKey) || '[]').map(String);
        const isBlocked = blockedUserIds.includes(String(memberId));

        const blockBtn = document.createElement('button');
        blockBtn.className = 'msg-context-menu-item';
        blockBtn.innerHTML = isBlocked ? '<i class="fas fa-user-slash"></i> Bỏ chặn' : '<i class="fas fa-ban"></i> Chặn';
        blockBtn.onclick = () => {
            if (isBlocked) {
                blockedUserIds = blockedUserIds.filter(id => id !== String(memberId));
                Swal.fire({ icon: 'success', title: `Đã bỏ chặn ${memberName}`, toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 });
            } else {
                blockedUserIds.push(String(memberId));
                Swal.fire({ icon: 'success', title: `Đã chặn ${memberName}`, toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 });
            }
            localStorage.setItem(blockedKey, JSON.stringify(blockedUserIds));
            
            // Tải lại lịch sử chat để lọc tức thì
            const convId = document.getElementById('current-conv-id')?.value;
            if (convId) {
                selectConversation(convId, document.getElementById('partner-name').innerText, document.getElementById('partner-avatar').src, document.getElementById('is-group-chat').value === '1');
            }
            menu.style.display = 'none';
        };
        menu.appendChild(blockBtn);

        // Xóa thành viên (Kick) nếu có quyền quản lý
        const myRole = window.myGroupRole;
        const isGroup = document.getElementById('is-group-chat')?.value === '1';
        if (isGroup && (myRole === 'owner' || (myRole === 'admin' && memberRole === 'member')) && memberRole !== 'owner') {
            const kickBtn = document.createElement('button');
            kickBtn.className = 'msg-context-menu-item danger';
            kickBtn.innerHTML = '<i class="fas fa-user-times"></i> Xoá khỏi nhóm';
            kickBtn.onclick = () => {
                const convId = document.getElementById('current-conv-id')?.value;
                removeGroupMember(convId, memberId, memberName);
                menu.style.display = 'none';
            };
            menu.appendChild(kickBtn);
        }
    } else {
        const infoItem = document.createElement('div');
        infoItem.style.cssText = 'padding:6px 12px; font-size:0.82rem; color:#64748b;';
        infoItem.innerText = 'Đây là tài khoản của bạn';
        menu.appendChild(infoItem);
    }

    menu.style.display = 'flex';
    const pageX = e.pageX || (e.touches && e.touches[0] ? e.touches[0].pageX : e.clientX + window.scrollX);
    const pageY = e.pageY || (e.touches && e.touches[0] ? e.touches[0].pageY : e.clientY + window.scrollY);
    
    let posX = pageX;
    let posY = pageY;
    const menuWidth = 160;
    const menuHeight = 110;
    if (posX + menuWidth > window.innerWidth + window.scrollX) posX = window.innerWidth + window.scrollX - menuWidth - 10;
    if (posY + menuHeight > window.innerHeight + window.scrollY) posY = window.innerHeight + window.scrollY - menuHeight - 10;

    menu.style.left = posX + 'px';
    menu.style.top = posY + 'px';
}
</script>

<?php require_once 'includes/footer.php'; ?>
