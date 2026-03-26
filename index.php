<?php
require_once 'db.php';
session_start();

// Fetch Real Stats
// 1. Total Chats (all messages)
$stmt = $pdo->query("SELECT COUNT(*) FROM messages");
$total_chats = $stmt->fetchColumn();

// 2. Active Users (unique sessions)
$stmt = $pdo->query("SELECT COUNT(DISTINCT session_id) FROM messages");
$active_users = $stmt->fetchColumn();

// 3. AI Responses
$stmt = $pdo->query("SELECT COUNT(*) FROM analytics WHERE event_name = 'bot_response'");
$ai_responses = $stmt->fetchColumn();

// 4. All messages for history view
$stmt = $pdo->query("SELECT * FROM messages ORDER BY timestamp DESC LIMIT 50");
$all_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Success Rate (mock logic for now: responses / requests)
$success_rate = $total_chats > 0 ? floor(($ai_responses / ($total_chats / 2)) * 100) : 0;
if ($success_rate > 100) $success_rate = 99; // Cap at 99 for realism
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Chat Dashboard</title>
    <style>
        /* =========================================
           CSS VARIABLES & RESET
           ========================================= */
        :root {
            /* Light Theme */
            --bg-color: #f3f4f6;
            --text-color: #1f2937;
            --sidebar-bg: rgba(255, 255, 255, 0.7);
            --sidebar-hover: rgba(255, 255, 255, 0.9);
            --card-bg: rgba(255, 255, 255, 0.6);
            --card-border: rgba(255, 255, 255, 0.4);
            --primary-gradient: linear-gradient(135deg, #6366f1 0%, #a855f7 100%);
            --accent-color: #8b5cf6;
            --shadow-soft: 0 4px 30px rgba(0, 0, 0, 0.1);
            --glass-border: 1px solid rgba(255, 255, 255, 0.5);
            --chat-user-bubble: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            --chat-bot-bubble: #ffffff;
            --input-bg: rgba(255, 255, 255, 0.8);
            --animate-speed: 0.3s;
        }

        [data-theme="dark"] {
            /* Dark Theme */
            --bg-color: #0f172a;
            --text-color: #e2e8f0;
            --sidebar-bg: rgba(30, 41, 59, 0.7);
            --sidebar-hover: rgba(51, 65, 85, 0.9);
            --card-bg: rgba(30, 41, 59, 0.6);
            --card-border: rgba(255, 255, 255, 0.05);
            --primary-gradient: linear-gradient(135deg, #818cf8 0%, #c084fc 100%);
            --accent-color: #c084fc;
            --shadow-soft: 0 4px 30px rgba(0, 0, 0, 0.5);
            --glass-border: 1px solid rgba(255, 255, 255, 0.05);
            --chat-user-bubble: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            --chat-bot-bubble: #1e293b;
            --input-bg: rgba(30, 41, 59, 0.8);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
        }

        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            overflow: hidden; /* Prevent body scroll, handle inside containers */
            height: 100vh;
            transition: background-color 0.5s ease, color 0.5s ease;
        }

        /* Animated Background */
        .background-blobs {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
            background: radial-gradient(circle at 10% 20%, rgba(99, 102, 241, 0.15) 0%, transparent 40%),
                        radial-gradient(circle at 90% 80%, rgba(168, 85, 247, 0.15) 0%, transparent 40%);
        }

        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.6;
            animation: float 10s infinite alternate ease-in-out;
        }

        .blob-1 { width: 400px; height: 400px; background: var(--accent-color); top: -100px; left: -100px; }
        .blob-2 { width: 300px; height: 300px; background: var(--primary-gradient); bottom: 0; right: -50px; animation-delay: -5s; }

        @keyframes float {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(30px, 50px) scale(1.1); }
        }

        /* =========================================
           LOADING SCREEN
           ========================================= */
        #loading-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--bg-color);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            transition: opacity 0.8s ease, visibility 0.8s ease;
        }

        .loader-text {
            font-size: 2rem;
            font-weight: 700;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            opacity: 0;
            transform: scale(0.9);
            animation: fadeInOut 2s ease-in-out forwards;
        }

        @keyframes fadeInOut {
            0% { opacity: 0; transform: scale(0.9); }
            20% { opacity: 1; transform: scale(1); }
            80% { opacity: 1; transform: scale(1); }
            100% { opacity: 0; transform: scale(1.1); }
        }

        /* =========================================
           LAYOUT
           ========================================= */
        .app-container {
            display: flex;
            height: 100vh;
            width: 100%;
            opacity: 0;
            transition: opacity 1s ease 1.5s; /* Delay visibility until load anim completes */
        }

        .app-container.visible {
            opacity: 1;
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: var(--sidebar-bg);
            backdrop-filter: blur(20px);
            border-right: var(--glass-border);
            display: flex;
            flex-direction: column;
            padding: 1.5rem;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 10;
        }

        .sidebar.collapsed {
            width: 80px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 3rem;
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--text-color);
            white-space: nowrap;
            overflow: hidden;
        }

        .logo-icon {
            width: 32px;
            height: 32px;
            background: var(--primary-gradient);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .nav-links {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            flex-grow: 1;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s ease;
            color: var(--text-color);
            text-decoration: none;
            white-space: nowrap;
        }

        .nav-item:hover {
            background: var(--sidebar-hover);
            transform: translateX(4px);
        }

        .nav-item.active {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }

        .nav-icon {
            width: 20px;
            height: 20px;
            stroke-width: 2;
            flex-shrink: 0;
        }

        .sidebar.collapsed .nav-text {
            opacity: 0;
            width: 0;
            pointer-events: none;
        }

        .sidebar.collapsed .logo-text {
            display: none;
        }

        .sidebar-footer {
            margin-top: auto;
            border-top: 1px solid rgba(128, 128, 128, 0.1);
            padding-top: 1rem;
        }

        /* Main Content */
        .main-content {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            position: relative;
        }

        /* Navbar */
        .navbar {
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            backdrop-filter: blur(10px);
        }

        .search-bar {
            position: relative;
            width: 300px;
        }

        .search-input {
            width: 100%;
            padding: 10px 16px 10px 40px;
            border-radius: 24px;
            border: var(--glass-border);
            background: var(--input-bg);
            color: var(--text-color);
            outline: none;
            transition: all 0.2s;
        }

        .search-input:focus {
            box-shadow: 0 0 0 2px var(--accent-color);
        }

        .search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            width: 18px;
            height: 18px;
            stroke: var(--text-color);
            opacity: 0.5;
        }

        .nav-actions {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .icon-btn {
            background: transparent;
            border: none;
            color: var(--text-color);
            cursor: pointer;
            position: relative;
            transition: transform 0.2s;
        }

        .icon-btn:hover {
            transform: scale(1.1);
        }

        .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            width: 8px;
            height: 8px;
            background: #ef4444;
            border-radius: 50%;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent-color);
        }

        /* Scrollable View Area */
        .view-container {
            flex-grow: 1;
            padding: 2rem;
            overflow-y: auto;
            scroll-behavior: smooth;
        }

        /* Custom Scrollbar */
        .view-container::-webkit-scrollbar {
            width: 8px;
        }
        .view-container::-webkit-scrollbar-track {
            background: transparent;
        }
        .view-container::-webkit-scrollbar-thumb {
            background-color: rgba(128, 128, 128, 0.2);
            border-radius: 20px;
        }

        /* =========================================
           DASHBOARD WIDGETS
           ========================================= */
        .dashboard-header {
            margin-bottom: 2rem;
        }

        .dashboard-title {
            font-size: 1.8rem;
            font-weight: 700;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--card-bg);
            border: var(--glass-border);
            padding: 1.5rem;
            border-radius: 16px;
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-soft);
            transition: transform 0.3s, box-shadow 0.3s;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        }

        .stat-info h3 {
            font-size: 0.9rem;
            opacity: 0.7;
            margin-bottom: 0.5rem;
        }

        .stat-info .value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-color);
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .icon-blue { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
        .icon-purple { background: rgba(168, 85, 247, 0.15); color: #a855f7; }
        .icon-green { background: rgba(34, 197, 94, 0.15); color: #22c55e; }
        .icon-orange { background: rgba(249, 115, 22, 0.15); color: #f97316; }

        /* =========================================
           CHAT AREA
           ========================================= */
        .chat-container {
            background: var(--card-bg);
            border: var(--glass-border);
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            height: 530px;
            overflow: hidden;
            box-shadow: var(--shadow-soft);
            backdrop-filter: blur(10px);
        }

        .chat-header {
            padding: 1rem 1.5rem;
            border-bottom: var(--glass-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .chat-messages {
            flex-grow: 1;
            padding: 1.5rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .message {
            max-width: 75%;
            padding: 12px 16px;
            border-radius: 16px;
            line-height: 1.5;
            position: relative;
            animation: slideUp 0.3s ease forwards;
            font-size: 0.95rem;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .message.bot {
            align-self: flex-start;
            background: var(--chat-bot-bubble);
            color: var(--text-color);
            border-bottom-left-radius: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }

        .message.user {
            align-self: flex-end;
            background: var(--chat-user-bubble);
            color: white;
            border-bottom-right-radius: 4px;
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
        }

        .typing-indicator {
            align-self: flex-start;
            background: var(--chat-bot-bubble);
            padding: 10px 16px;
            border-radius: 16px;
            border-bottom-left-radius: 4px;
            display: none; /* Hidden by default */
            gap: 4px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            width: fit-content;
        }

        .typing-dot {
            width: 8px;
            height: 8px;
            background: #cbd5e1;
            border-radius: 50%;
            animation: bounce 1.4s infinite ease-in-out both;
        }

        .typing-dot:nth-child(1) { animation-delay: -0.32s; }
        .typing-dot:nth-child(2) { animation-delay: -0.16s; }

        @keyframes bounce {
            0%, 80%, 100% { transform: scale(0); }
            40% { transform: scale(1); }
        }

        .chat-input-area {
            padding: 1.5rem;
            border-top: var(--glass-border);
            display: flex;
            gap: 10px;
            background: var(--input-bg);
        }

        .chat-input {
            flex-grow: 1;
            padding: 12px 16px;
            border-radius: 24px;
            border: 1px solid rgba(128, 128, 128, 0.1);
            background: var(--card-bg);
            color: var(--text-color);
            outline: none;
            resize: none;
            height: 50px; /* Single line default */
            line-height: 26px;
        }

        .send-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--primary-gradient);
            border: none;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .send-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 0 15px rgba(168, 85, 247, 0.4);
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .sidebar {
                width: 80px;
            }
            .sidebar .nav-text, .sidebar .logo-text {
                display: none;
            }
            .sidebar .logo {
                justify-content: center;
                margin-bottom: 2rem;
            }
            .sidebar .nav-item {
                justify-content: center;
                padding: 12px;
            }
            .sidebar .nav-icon {
                margin: 0;
            }
        }

        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -260px;
                top: 0;
                bottom: 0;
                width: 260px !important;
                height: 100%;
                z-index: 1000;
                box-shadow: 10px 0 30px rgba(0,0,0,0.3);
                transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
            .sidebar.mobile-open {
                left: 0;
            }
            .sidebar .nav-text, .sidebar .logo-text {
                display: block;
            }
            .sidebar .logo {
                justify-content: flex-start;
            }
            .sidebar .nav-item {
                justify-content: flex-start;
            }

            .main-content {
                width: 100%;
            }

            .navbar {
                padding: 0 1rem;
            }
            
            #menu-toggle {
                display: flex !important;
                align-items: center;
                justify-content: center;
            }

            .search-bar {
                display: none; /* Hide search on small screens */
            }

            .view-container {
                padding: 1.5rem 1rem 5rem 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-title {
                font-size: 1.5rem;
            }
            
            .chat-container {
                height: 450px;
                margin-bottom: 3rem;
            }
        }

        /* Overlay for mobile sidebar */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 999;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .sidebar-overlay.active {
            display: block;
            opacity: 1;
        }

        /* Responsive Table */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            margin-top: 1rem;
            border-radius: 12px;
            box-shadow: var(--shadow-soft);
        }

        .data-table {
            margin-top: 0;
            min-width: 600px;
        }

        @media (max-width: 480px) {
            .nav-actions {
                gap: 0.8rem;
            }
            .stat-card {
                padding: 1rem;
            }
            .stat-info .value {
                font-size: 1.5rem;
            }
            .chat-header {
                padding: 0.8rem 1rem;
            }
            .chat-input-area {
                padding: 1rem;
            }
            .message {
                max-width: 85%;
            }
            .dashboard-header p {
                font-size: 0.85rem;
            }
        }
        /* Views Visibility */
        .view-section {
            display: none;
            animation: fadeIn 0.5s ease forwards;
        }

        .view-section.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Chats List Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
            border-radius: 12px;
            overflow: hidden;
            backdrop-filter: blur(10px);
            border: var(--glass-border);
            margin-top: 1rem;
        }

        .data-table th, .data-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(128, 128, 128, 0.1);
        }

        .data-table th {
            background: rgba(128, 128, 128, 0.05);
            font-weight: 600;
            opacity: 0.8;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .data-table tr:hover {
            background: rgba(128, 128, 128, 0.03);
        }

        .badge-user { background: #6366f1; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; }
        .badge-bot { background: #a855f7; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.75rem; }

    </style>

</head>
<body>

    <!-- Animated Background -->
    <div class="background-blobs">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
    </div>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <!-- Loading Screen -->
    <div id="loading-screen">
        <div class="loader-text">Welcome to ANVI AI</div>
    </div>

    <!-- App Container -->
    <div class="app-container" id="app">
        
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="logo">
                <div class="logo-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"></path>
                        <path d="M8.5 8.5v.01"></path>
                        <path d="M16 15.5v.01"></path>
                        <path d="M12 12v.01"></path>
                    </svg>
                </div>
                <span class="logo-text">ANVI AI</span>
            </div>

            <ul class="nav-links">
                <li class="nav-item active" data-section="dashboard-view">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    <span class="nav-text">Dashboard</span>
                </li>
                <li class="nav-item" data-section="chats-view">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                    <span class="nav-text">Chats</span>
                </li>
                <li class="nav-item" data-section="analytics-view">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"></line><line x1="12" y1="20" x2="12" y2="4"></line><line x1="6" y1="20" x2="6" y2="14"></line></svg>
                    <span class="nav-text">Analytics</span>
                </li>
                <li class="nav-item" data-section="settings-view">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                    <span class="nav-text">Settings</span>
                </li>
            </ul>


            <div class="sidebar-footer">
                <li class="nav-item">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    <span class="nav-text">Logout</span>
                </li>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            
            <!-- Navbar -->
            <nav class="navbar">
                <button class="icon-btn" id="menu-toggle" style="margin-right: 10px; display: none;">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                </button>

                <div class="search-bar">
                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
                    <input type="text" class="search-input" placeholder="Search chats...">
                </div>

                <div class="nav-actions">
                    <button class="icon-btn" id="theme-toggle" aria-label="Toggle Theme">
                        <svg class="sun-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                        <svg class="moon-icon" style="display: none;" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
                    </button>
                    <button class="icon-btn">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                        <span class="badge"></span>
                    </button>
                    <div class="user-profile">
                        <img src="https://picsum.photos/seed/user123/100/100" alt="User" class="avatar">
                    </div>
                </div>
            </nav>

            <!-- View Container -->
            <div class="view-container">
                
                <!-- DASHBOARD VIEW -->
                <div id="dashboard-view" class="view-section active">
                    <div class="dashboard-header">
                        <h1 class="dashboard-title">Overview</h1>
                        <p style="opacity: 0.6;">Welcome back! Here is your AI performance summary.</p>
                    </div>

                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-info">
                                <h3>Total Chats</h3>
                                <div class="value" id="stat-total-chats" data-target="<?php echo $total_chats; ?>">0</div>
                            </div>
                            <div class="stat-icon icon-blue">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-info">
                                <h3>Active Users</h3>
                                <div class="value" id="stat-active-users" data-target="<?php echo $active_users; ?>">0</div>
                            </div>
                            <div class="stat-icon icon-purple">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-info">
                                <h3>AI Responses</h3>
                                <div class="value" id="stat-ai-responses" data-target="<?php echo $ai_responses; ?>">0</div>
                            </div>
                            <div class="stat-icon icon-green">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-info">
                                <h3>Success Rate</h3>
                                <div class="value"><span id="stat-success-rate" data-target="<?php echo $success_rate; ?>">0</span>%</div>
                            </div>
                            <div class="stat-icon icon-orange">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
                            </div>
                        </div>
                    </div>

                    <h2 class="dashboard-title" style="margin-bottom: 1rem;">New Session</h2>
                    <div class="chat-container">
                        <div class="chat-header">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 10px; height: 10px; background: #22c55e; border-radius: 50%;"></div>
                                <strong>Anvi AI</strong>
                            </div>
                            <button class="icon-btn">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="1"></circle><circle cx="12" cy="5" r="1"></circle><circle cx="12" cy="19" r="1"></circle></svg>
                            </button>
                        </div>
                        <div class="chat-messages" id="chat-messages">
                            <div class="message bot">Hello! I'm Anvi, your AI assistant. How can I help you optimize your workflow today?</div>
                        </div>
                        <div class="typing-indicator" id="typing-indicator">
                            <div class="typing-dot"></div>
                            <div class="typing-dot"></div>
                            <div class="typing-dot"></div>
                        </div>
                        <div class="chat-input-area">
                            <input type="text" class="chat-input" id="chat-input" placeholder="Type a message...">
                            <button class="send-btn" id="send-btn">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- CHATS VIEW -->
                <div id="chats-view" class="view-section">
                    <div class="dashboard-header">
                        <h1 class="dashboard-title">Chat Records</h1>
                        <p style="opacity: 0.6;">History of all interactions across sessions.</p>
                    </div>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>Sender</th>
                                    <th>Message</th>
                                    <th>Session ID</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_messages as $msg): ?>
                                <tr>
                                    <td style="font-size: 0.8rem; opacity: 0.6;"><?php echo $msg['timestamp']; ?></td>
                                    <td><span class="badge-<?php echo $msg['sender']; ?>"><?php echo ucfirst($msg['sender']); ?></span></td>
                                    <td><?php echo htmlspecialchars(substr($msg['content'], 0, 100)) . (strlen($msg['content']) > 100 ? '...' : ''); ?></td>
                                    <td style="font-family: monospace; font-size: 0.8rem;"><?php echo substr($msg['session_id'], 0, 8); ?>...</td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($all_messages)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center; padding: 2rem; opacity: 0.5;">No chat records found. Start a conversation!</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- SETTINGS VIEW -->
                <div id="settings-view" class="view-section">
                    <div class="dashboard-header">
                        <h1 class="dashboard-title">System Settings</h1>
                        <p style="opacity: 0.6;">Manage your AI configurations and profile.</p>
                    </div>

                    <div class="settings-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem;">
                        <div class="stat-card" style="display: block;">
                            <h3 style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"></circle><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path></svg>
                                General Settings
                            </h3>
                            <div style="display: flex; flex-direction: column; gap: 1rem;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span>AI Model Selection</span>
                                    <select style="background: var(--input-bg); color: var(--text-color); border: var(--glass-border); padding: 5px 10px; border-radius: 8px;">
                                        <option>Llama 3-8B</option>
                                        <option>GPT-4o (Coming Soon)</option>
                                    </select>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span>Auto-archive Chats</span>
                                    <input type="checkbox" checked style="accent-color: var(--accent-color);">
                                </div>
                            </div>
                        </div>

                        <div class="stat-card" style="display: block;">
                            <h3 style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px;">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                                Profile Information
                            </h3>
                            <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 1.5rem;">
                                <img src="https://picsum.photos/seed/user123/100/100" alt="User" style="width: 50px; height: 50px; border-radius: 50%; border: 2px solid var(--accent-color);">
                                <div>
                                    <div style="font-weight: 600;">Admin User</div>
                                    <div style="font-size: 0.8rem; opacity: 0.6;">admin@anvi.ai</div>
                                </div>
                            </div>
                            <button style="width: 100%; padding: 10px; border-radius: 8px; background: var(--primary-gradient); color: white; border: none; cursor: pointer; font-weight: 600;">Edit Profile</button>
                        </div>
                    </div>
                </div>
                <div id="analytics-view" class="view-section">
                    <div class="dashboard-header">
                        <h1 class="dashboard-title">Data Analytics</h1>
                        <p style="opacity: 0.6;">Real-time performance metrics and usage data.</p>
                    </div>
                    
                    <div class="stats-grid">
                        <div class="stat-card" style="grid-column: span 2;">
                            <div class="stat-info">
                                <h3>Total AI Usage</h3>
                                <p style="font-size: 0.9rem; opacity: 0.7; margin-top: 10px;">
                                    The AI has generated <strong><?php echo $ai_responses; ?></strong> responses out of 
                                    <strong><?php echo $total_chats; ?></strong> total exchange units.
                                </p>
                                <div style="height: 10px; width: 100%; background: rgba(128,128,128,0.1); border-radius: 5px; margin-top: 15px; overflow: hidden;">
                                    <div style="height: 100%; width: <?php echo $success_rate; ?>%; background: var(--primary-gradient);"></div>
                                </div>
                                <p style="font-size: 0.8rem; margin-top: 10px; opacity: 0.5;">Efficiency Rate: <?php echo $success_rate; ?>%</p>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-info">
                                <h3>Unique Users</h3>
                                <div class="value" data-target="<?php echo $active_users; ?>">0</div>
                                <p style="font-size: 0.8rem; opacity: 0.5;">Based on session tracking.</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </main>
    </div>

    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            
            // ==========================================
            // 1. LOADING SCREEN LOGIC
            // ==========================================
            const loadingScreen = document.getElementById('loading-screen');
            const appContainer = document.getElementById('app');

            // Simulate loading for 2.5 seconds
            setTimeout(() => {
                loadingScreen.style.opacity = '0';
                loadingScreen.style.visibility = 'hidden';
                
                appContainer.classList.add('visible');
                
                // Trigger number counters
                animateCounters();
            }, 2500);


            // ==========================================
            // 2. THEME MANAGEMENT
            // ==========================================
            const themeToggle = document.getElementById('theme-toggle');
            const sunIcon = document.querySelector('.sun-icon');
            const moonIcon = document.querySelector('.moon-icon');
            
            // Check localStorage
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.body.setAttribute('data-theme', savedTheme);
            updateIcons(savedTheme);

            themeToggle.addEventListener('click', () => {
                const currentTheme = document.body.getAttribute('data-theme');
                const newTheme = currentTheme === 'light' ? 'dark' : 'light';
                
                document.body.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);
                updateIcons(newTheme);
            });

            function updateIcons(theme) {
                if (theme === 'dark') {
                    sunIcon.style.display = 'block';
                    moonIcon.style.display = 'none';
                } else {
                    sunIcon.style.display = 'none';
                    moonIcon.style.display = 'block';
                }
            }


            // ==========================================
            // 3. NAVIGATION LOGIC
            // ==========================================
            const navItems = document.querySelectorAll('.nav-item[data-section]');
            const sections = document.querySelectorAll('.view-section');

            navItems.forEach(item => {
                item.addEventListener('click', () => {
                    const targetSection = item.getAttribute('data-section');
                    
                    // Remove active classes
                    navItems.forEach(nav => nav.classList.remove('active'));
                    sections.forEach(sec => sec.classList.remove('active'));
                    
                    // Add active classes
                    item.classList.add('active');
                    document.getElementById(targetSection).classList.add('active');
                    
                    // If dashboard, re-animate counters
                    if (targetSection === 'dashboard-view') {
                        animateCounters();
                    }
                });
            });

            const sidebar = document.getElementById('sidebar');
            const menuToggle = document.getElementById('menu-toggle');
            const sidebarOverlay = document.getElementById('sidebar-overlay');

            function toggleMobileMenu() {
                sidebar.classList.toggle('mobile-open');
                sidebarOverlay.classList.toggle('active');
            }

            menuToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                if (window.innerWidth <= 768) {
                    toggleMobileMenu();
                } else {
                    sidebar.classList.toggle('collapsed');
                }
            });

            sidebarOverlay.addEventListener('click', () => {
                if (sidebar.classList.contains('mobile-open')) {
                    toggleMobileMenu();
                }
            });

            // Close sidebar when clicking a nav item on mobile
            navItems.forEach(item => {
                item.addEventListener('click', () => {
                    if (window.innerWidth <= 768 && sidebar.classList.contains('mobile-open')) {
                        toggleMobileMenu();
                    }
                });
            });


            // ==========================================
            // 4. NUMBER COUNTER ANIMATION
            // ==========================================
            function animateCounters() {
                const counters = document.querySelectorAll('.stat-info .value');
                
                counters.forEach(counter => {
                    const targetContent = counter.innerText.includes('%') ? counter.querySelector('span').getAttribute('data-target') : counter.getAttribute('data-target');
                    const target = +targetContent;
                    const duration = 2000; // 2 seconds
                    const increment = target / (duration / 16); // 60fps
                    
                    let current = 0;
                    const updateCounter = () => {
                        current += increment;
                        if (current < target) {
                            if (counter.innerText.includes('%')) {
                                counter.querySelector('span').innerText = Math.ceil(current).toLocaleString();
                            } else {
                                counter.innerText = Math.ceil(current).toLocaleString();
                            }
                            requestAnimationFrame(updateCounter);
                        } else {
                            if (counter.innerText.includes('%')) {
                                counter.querySelector('span').innerText = target.toLocaleString();
                            } else {
                                counter.innerText = target.toLocaleString();
                            }
                        }
                    };
                    updateCounter();
                });
            }


            // ==========================================
            // 5. CHATBOT FUNCTIONALITY
            // ==========================================
            const chatInput = document.getElementById('chat-input');
            const sendBtn = document.getElementById('send-btn');
            const chatMessages = document.getElementById('chat-messages');
            const typingIndicator = document.getElementById('typing-indicator');

            function addMessage(text, sender) {
                const msgDiv = document.createElement('div');
                msgDiv.classList.add('message', sender);
                msgDiv.innerText = text;
                chatMessages.appendChild(msgDiv);
                
                // Auto scroll to bottom
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }

            function handleSend() {
                const text = chatInput.value.trim();
                if (!text) return;

                // Add User Message
                addMessage(text, 'user');
                chatInput.value = '';

                // Show Typing Indicator
                typingIndicator.style.display = 'flex';
                chatMessages.scrollTop = chatMessages.scrollHeight;

                // Fetch response from chatbot.php
                fetch("chatbot.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/x-www-form-urlencoded"
                    },
                    body: "message=" + encodeURIComponent(text)
                })
                .then(res => res.json())
                .then(data => {
                    typingIndicator.style.display = 'none';
                    addMessage(data.message, 'bot');
                    
                    // Update stats in real-time
                    if (data.stats) {
                        updateStat('stat-total-chats', data.stats.total_chats);
                        updateStat('stat-active-users', data.stats.active_users);
                        updateStat('stat-ai-responses', data.stats.ai_responses);
                        updateStat('stat-success-rate', data.stats.success_rate, true);
                    }
                })
                .catch(err => {
                    console.error("Error:", err);
                    typingIndicator.style.display = 'none';
                    addMessage("Sorry, I encountered an error. Please try again.", 'bot');
                });
            }

            function updateStat(id, newValue, isRate = false) {
                const el = document.getElementById(id);
                if (!el) return;
                
                // Update data-target for the next animation trigger
                el.setAttribute('data-target', newValue);
                
                // Animate to new value
                const startValue = parseInt(el.innerText) || 0;
                const duration = 1000;
                const startTime = performance.now();
                
                const animate = (currentTime) => {
                    const elapsed = currentTime - startTime;
                    const progress = Math.min(elapsed / duration, 1);
                    const currentVal = Math.floor(startValue + (newValue - startValue) * progress);
                    
                    el.innerText = currentVal.toLocaleString();
                    
                    if (progress < 1) {
                        requestAnimationFrame(animate);
                    } else {
                        el.innerText = newValue.toLocaleString();
                    }
                };
                requestAnimationFrame(animate);
            }

            sendBtn.addEventListener('click', handleSend);
            chatInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') handleSend();
            });

        });
    </script>
</body>
</html>
