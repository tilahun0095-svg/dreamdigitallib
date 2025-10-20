<?php
require_once 'config.php';

class DownloadManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function recordDownload($bookId, $studentId) {
        requireLogin();
        
        // Check if book exists
        $book = $this->pdo->prepare("SELECT * FROM books WHERE id = ?");
        $book->execute([$bookId]);
        $book = $book->fetch(PDO::FETCH_ASSOC);
        
        if (!$book) {
            jsonResponse(false, 'Book not found');
        }
        
        // Record download
        $stmt = $this->pdo->prepare("INSERT INTO download_history (student_id, book_id) VALUES (?, ?)");
        $stmt->execute([$studentId, $bookId]);
        
        return $book;
    }
    
    public function getDownloadHistory($userId) {
        requireLogin();
        
        $sql = "SELECT dh.*, bk.title, bk.author, bk.cover_page
                FROM download_history dh
                JOIN books bk ON dh.book_id = bk.id
                WHERE dh.student_id = ?
                ORDER BY dh.download_date DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function downloadBook($bookId) {
        requireLogin();
        
        $book = $this->recordDownload($bookId, $_SESSION['user_id']);
        
        if ($book && file_exists($book['pdf_path'])) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . basename($book['pdf_path']) . '"');
            header('Content-Length: ' . filesize($book['pdf_path']));
            readfile($book['pdf_path']);
            exit;
        } else {
            jsonResponse(false, 'File not found');
        }
    }
}

// Handle download requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $downloadManager = new DownloadManager($pdo);
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'record_download':
            if (empty($_POST['book_id'])) {
                jsonResponse(false, 'Book ID is required');
            }
            $downloadManager->recordDownload($_POST['book_id'], $_SESSION['user_id']);
            jsonResponse(true, 'Download recorded');
            break;
            
        case 'get_download_history':
            $history = $downloadManager->getDownloadHistory($_SESSION['user_id']);
            jsonResponse(true, '', $history);
            break;
            
        default:
            jsonResponse(false, 'Invalid action');
    }
}

// Handle file download
if (isset($_GET['download']) && is_numeric($_GET['download'])) {
    requireLogin();
    $downloadManager = new DownloadManager($pdo);
    $downloadManager->downloadBook($_GET['download']);
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Library</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3498db;
            --secondary: #2c3e50;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --light: #ecf0f1;
            --dark: #34495e;
            --sidebar-width: 250px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--secondary);
            color: white;
            height: 100vh;
            position: fixed;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 1.5rem 1rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .sidebar-header i {
            font-size: 1.8rem;
            color: var(--primary);
        }
        
        .sidebar-header h1 {
            font-size: 1.3rem;
        }
        
        .sidebar-menu {
            padding: 1rem 0;
        }
        
        .menu-item {
            padding: 0.8rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #bdc3c7;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        
        .menu-item:hover, .menu-item.active {
            background: rgba(52, 152, 219, 0.1);
            color: white;
            border-left-color: var(--primary);
        }
        
        .menu-item i {
            width: 20px;
            text-align: center;
        }
        
        .menu-section {
            padding: 1rem 1.5rem 0.5rem;
            font-size: 0.8rem;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            padding: 1rem;
            transition: all 0.3s;
        }
        
        .top-bar {
            background: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        /* Dashboard */
        .dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 1.5rem;
            transition: transform 0.3s;
            border-top: 4px solid var(--primary);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card.book {
            border-top-color: var(--primary);
        }
        
        .stat-card.download {
            border-top-color: var(--success);
        }
        
        .stat-card.borrow {
            border-top-color: var(--warning);
        }
        
        .stat-card.overdue {
            border-top-color: var(--danger);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .stat-card.book .stat-icon {
            color: var(--primary);
        }
        
        .stat-card.download .stat-icon {
            color: var(--success);
        }
        
        .stat-card.borrow .stat-icon {
            color: var(--warning);
        }
        
        .stat-card.overdue .stat-icon {
            color: var(--danger);
        }
        
        .stat-card h3 {
            font-size: 0.9rem;
            color: #7f8c8d;
            margin-bottom: 0.5rem;
        }
        
        .stat-card .number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--secondary);
        }
        
        /* Books Grid */
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 2rem;
        }
        
        .book-card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.3s;
        }
        
        .book-card:hover {
            transform: translateY(-5px);
        }
        
        .book-cover {
            height: 200px;
            background-color: #eee;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
            position: relative;
            overflow: hidden;
        }
        
        .book-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        
        .book-card:hover .book-cover img {
            transform: scale(1.05);
        }
        
        .book-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--primary);
            color: white;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
        }
        
        .book-info {
            padding: 1rem;
        }
        
        .book-title {
            font-weight: bold;
            margin-bottom: 5px;
            color: var(--secondary);
            font-size: 1rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .book-author {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 10px;
        }
        
        .book-meta {
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            color: #7f8c8d;
            margin-bottom: 10px;
        }
        
        .book-actions {
            display: flex;
            justify-content: space-between;
            gap: 5px;
        }
        
        /* Buttons */
        .btn {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.7rem;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #2980b9;
        }
        
        .btn-secondary {
            background-color: var(--secondary);
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #1a252f;
        }
        
        .btn-success {
            background-color: var(--success);
            color: white;
        }
        
        .btn-success:hover {
            background-color: #27ae60;
        }
        
        .btn-danger {
            background-color: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #c0392b;
        }
        
        .btn-warning {
            background-color: var(--warning);
            color: white;
        }
        
        .btn-warning:hover {
            background-color: #e67e22;
        }
        
        .btn-light {
            background-color: var(--light);
            color: var(--dark);
        }
        
        .btn-light:hover {
            background-color: #dde4e6;
        }
        
        /* Forms */
        .form-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--secondary);
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        
        /* Tables */
        .table-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background-color: var(--light);
            color: var(--secondary);
            font-weight: 600;
        }
        
        tr:hover {
            background-color: #f9f9f9;
        }
        
        /* Tabs */
        .tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 1.5rem;
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
        }
        
        .tab.active {
            border-bottom-color: var(--primary);
            color: var(--primary);
            font-weight: 600;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* Search Bar */
        .search-bar {
            display: flex;
            margin-bottom: 1.5rem;
        }
        
        .search-bar input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px 0 0 4px;
            font-size: 1rem;
        }
        
        .search-bar button {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0 20px;
            border-radius: 0 4px 4px 0;
            cursor: pointer;
        }
        
        /* Status badges */
        .status-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            font-weight: bold;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-approved {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .status-returned {
            background: #d4edda;
            color: #155724;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 2000;
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: white;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            color: var(--secondary);
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #7f8c8d;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        /* Page Sections */
        .page-section {
            display: none;
        }
        
        .page-section.active {
            display: block;
        }
        
        .section-title {
            margin-bottom: 1.5rem;
            color: var(--secondary);
            border-bottom: 2px solid var(--light);
            padding-bottom: 10px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                overflow: visible;
            }
            
            .sidebar-header h1, .menu-item span, .menu-section {
                display: none;
            }
            
            .sidebar-header {
                justify-content: center;
                padding: 1rem 0.5rem;
            }
            
            .menu-item {
                padding: 0.8rem;
                justify-content: center;
            }
            
            .main-content {
                margin-left: 70px;
            }
            
            .dashboard {
                grid-template-columns: 1fr;
            }
            
            .books-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }
        
        /* Loading Spinner */
        .spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            border-left-color: var(--primary);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-book-open"></i>
            <h1>Digital Library</h1>
        </div>
        
        <div class="sidebar-menu">
            <div class="menu-section">Main</div>
            <a href="#" class="menu-item active" data-page="dashboard">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            <a href="#" class="menu-item" data-page="books">
                <i class="fas fa-book"></i>
                <span>Browse Books</span>
            </a>
            <a href="#" class="menu-item" data-page="my-books">
                <i class="fas fa-bookmark"></i>
                <span>My Books</span>
            </a>
            
            <div class="menu-section admin-section">Administration</div>
            <a href="#" class="menu-item admin-only" data-page="manage-books">
                <i class="fas fa-cog"></i>
                <span>Manage Books</span>
            </a>
            <a href="#" class="menu-item admin-only" data-page="manage-users">
                <i class="fas fa-users"></i>
                <span>Manage Users</span>
            </a>
            <a href="#" class="menu-item admin-only" data-page="borrow-requests">
                <i class="fas fa-clipboard-list"></i>
                <span>Borrow Requests</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <div class="search-bar">
                <input type="text" id="global-search" placeholder="Search books, authors...">
                <button id="search-btn"><i class="fas fa-search"></i></button>
            </div>
            
            <div class="user-info">
                <div class="user-avatar" id="user-avatar">JD</div>
                <div>
                    <div id="user-name">John Doe (Student)</div>
                    <div id="user-department">Computer Science</div>
                </div>
                <button class="btn btn-secondary" id="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </button>
            </div>
        </div>

        <!-- Dashboard Page -->
        <div id="dashboard" class="page-section active">
            <h2 class="section-title">Dashboard Overview</h2>
            
            <div class="dashboard">
                <div class="stat-card book">
                    <div class="stat-icon">
                        <i class="fas fa-book"></i>
                    </div>
                    <h3>Total Books</h3>
                    <div class="number" id="total-books">1,250</div>
                </div>
                
                <div class="stat-card download">
                    <div class="stat-icon">
                        <i class="fas fa-download"></i>
                    </div>
                    <h3>Downloads</h3>
                    <div class="number" id="total-downloads">342</div>
                </div>
                
                <div class="stat-card borrow">
                    <div class="stat-icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <h3>Borrowed Books</h3>
                    <div class="number" id="borrowed-books">5</div>
                </div>
                
                <div class="stat-card overdue">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3>Overdue</h3>
                    <div class="number" id="overdue-books">0</div>
                </div>
            </div>
            
            <h3 class="section-title">Recently Added Books</h3>
            <div class="books-grid" id="recent-books">
                <!-- Recent books will be populated by JavaScript -->
            </div>
            
            <h3 class="section-title">Popular Books</h3>
            <div class="books-grid" id="popular-books">
                <!-- Popular books will be populated by JavaScript -->
            </div>
        </div>

        <!-- Books Page -->
        <div id="books" class="page-section">
            <h2 class="section-title">Browse Books</h2>
            
            <div class="search-bar">
                <input type="text" id="book-search" placeholder="Search by title, author, or department...">
                <button id="book-search-btn"><i class="fas fa-search"></i> Search</button>
                <select id="department-filter" class="form-control" style="margin-left: 10px; width: auto;">
                    <option value="">All Departments</option>
                    <option value="Computer Science">Computer Science</option>
                    <option value="Mathematics">Mathematics</option>
                    <option value="Physics">Physics</option>
                    <option value="Chemistry">Chemistry</option>
                    <option value="Biology">Biology</option>
                    <option value="Literature">Literature</option>
                    <option value="History">History</option>
                </select>
            </div>
            
            <div class="books-grid" id="all-books">
                <!-- All books will be populated by JavaScript -->
            </div>
        </div>

        <!-- My Books Page -->
        <div id="my-books" class="page-section">
            <h2 class="section-title">My Books</h2>
            
            <div class="tabs">
                <div class="tab active" data-tab="borrowed">Currently Borrowed</div>
                <div class="tab" data-tab="downloads">Download History</div>
                <div class="tab" data-tab="requests">Borrowing Requests</div>
            </div>
            
            <div class="tab-content active" id="borrowed-tab">
                <div class="table-container">
                    <table id="borrowed-books-table">
                        <thead>
                            <tr>
                                <th>Book Title</th>
                                <th>Author</th>
                                <th>Borrow Date</th>
                                <th>Due Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Borrowed books will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="tab-content" id="downloads-tab">
                <div class="table-container">
                    <table id="download-history-table">
                        <thead>
                            <tr>
                                <th>Book Title</th>
                                <th>Author</th>
                                <th>Download Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Download history will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="tab-content" id="requests-tab">
                <div class="table-container">
                    <table id="borrow-requests-table">
                        <thead>
                            <tr>
                                <th>Book Title</th>
                                <th>Author</th>
                                <th>Request Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Borrow requests will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Manage Books Page (Admin/Librarian) -->
        <div id="manage-books" class="page-section admin-only">
            <h2 class="section-title">Manage Books</h2>
            
            <div class="tabs">
                <div class="tab active" data-tab="add-book">Add New Book</div>
                <div class="tab" data-tab="book-list">Book List</div>
            </div>
            
            <div class="tab-content active" id="add-book-tab">
                <div class="form-container">
                    <form id="add-book-form">
                        <div class="form-group">
                            <label for="book-title">Book Title</label>
                            <input type="text" id="book-title" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="book-author">Author</label>
                            <input type="text" id="book-author" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="book-isbn">ISBN/Book Number</label>
                            <input type="text" id="book-isbn" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="book-edition">Edition</label>
                            <input type="text" id="book-edition" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="book-department">Department</label>
                            <select id="book-department" class="form-control" required>
                                <option value="">Select Department</option>
                                <option value="Computer Science">Computer Science</option>
                                <option value="Mathematics">Mathematics</option>
                                <option value="Physics">Physics</option>
                                <option value="Chemistry">Chemistry</option>
                                <option value="Biology">Biology</option>
                                <option value="Literature">Literature</option>
                                <option value="History">History</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="book-cover">Cover Image</label>
                            <input type="file" id="book-cover" class="form-control" accept="image/*">
                        </div>
                        <div class="form-group">
                            <label for="book-pdf">PDF File</label>
                            <input type="file" id="book-pdf" class="form-control" accept=".pdf">
                        </div>
                        <button type="submit" class="btn btn-primary">Add Book</button>
                    </form>
                </div>
            </div>
            
            <div class="tab-content" id="book-list-tab">
                <div class="table-container">
                    <table id="admin-book-list">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Author</th>
                                <th>ISBN</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Admin book list will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Manage Users Page (Admin) -->
        <div id="manage-users" class="page-section admin-only">
            <h2 class="section-title">Manage Users</h2>
            
            <div class="tabs">
                <div class="tab active" data-tab="add-user">Add New User</div>
                <div class="tab" data-tab="user-list">User List</div>
            </div>
            
            <div class="tab-content active" id="add-user-tab">
                <div class="form-container">
                    <form id="add-user-form">
                        <div class="form-group">
                            <label for="user-role">User Role</label>
                            <select id="user-role" class="form-control" required>
                                <option value="">Select Role</option>
                                <option value="student">Student</option>
                                <option value="librarian">Librarian</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="user-fullname">Full Name</label>
                            <input type="text" id="user-fullname" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="user-email">Email</label>
                            <input type="email" id="user-email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="user-password">Password</label>
                            <input type="password" id="user-password" class="form-control" required>
                        </div>
                        <div class="form-group student-fields">
                            <label for="user-age">Age</label>
                            <input type="number" id="user-age" class="form-control">
                        </div>
                        <div class="form-group student-fields">
                            <label for="user-gender">Gender</label>
                            <select id="user-gender" class="form-control">
                                <option value="">Select Gender</option>
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group student-fields">
                            <label for="user-department">Department</label>
                            <select id="user-department" class="form-control">
                                <option value="">Select Department</option>
                                <option value="Computer Science">Computer Science</option>
                                <option value="Mathematics">Mathematics</option>
                                <option value="Physics">Physics</option>
                                <option value="Chemistry">Chemistry</option>
                                <option value="Biology">Biology</option>
                                <option value="Literature">Literature</option>
                                <option value="History">History</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Add User</button>
                    </form>
                </div>
            </div>
            
            <div class="tab-content" id="user-list-tab">
                <div class="table-container">
                    <table id="admin-user-list">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Admin user list will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Borrow Requests Page (Admin/Librarian) -->
        <div id="borrow-requests" class="page-section admin-only">
            <h2 class="section-title">Borrow Requests</h2>
            
            <div class="tabs">
                <div class="tab active" data-tab="pending-requests">Pending Requests</div>
                <div class="tab" data-tab="all-requests">All Requests</div>
            </div>
            
            <div class="tab-content active" id="pending-requests-tab">
                <div class="table-container">
                    <table id="pending-requests-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Book Title</th>
                                <th>Author</th>
                                <th>Request Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Pending requests will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="tab-content" id="all-requests-tab">
                <div class="table-container">
                    <table id="all-requests-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Book Title</th>
                                <th>Author</th>
                                <th>Request Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- All requests will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Login Modal -->
    <div id="login-modal" class="modal" style="display: flex;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Login to Digital Library</h2>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="login-form">
                    <div class="form-group">
                        <label for="login-email">Email</label>
                        <input type="email" id="login-email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="login-password">Password</label>
                        <input type="password" id="login-password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="login-role">Login as</label>
                        <select id="login-role" class="form-control" required>
                            <option value="">Select Role</option>
                            <option value="student">Student</option>
                            <option value="librarian">Librarian</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
                </form>
                <p style="text-align: center; margin-top: 15px;">
                    <a href="#" id="show-register">Don't have an account? Register here</a>
                </p>
            </div>
        </div>
    </div>

    <!-- Register Modal -->
    <div id="register-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Student Registration</h2>
                <button class="close-modal">&times;</button>
            </div>
            <div class="modal-body">
                <form id="register-form">
                    <div class="form-group">
                        <label for="reg-fullname">Full Name</label>
                        <input type="text" id="reg-fullname" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="reg-age">Age</label>
                        <input type="number" id="reg-age" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="reg-gender">Gender</label>
                        <select id="reg-gender" class="form-control" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="reg-department">Department</label>
                        <select id="reg-department" class="form-control" required>
                            <option value="">Select Department</option>
                            <option value="Computer Science">Computer Science</option>
                            <option value="Mathematics">Mathematics</option>
                            <option value="Physics">Physics</option>
                            <option value="Chemistry">Chemistry</option>
                            <option value="Biology">Biology</option>
                            <option value="Literature">Literature</option>
                            <option value="History">History</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="reg-email">Email</label>
                        <input type="email" id="reg-email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="reg-password">Password</label>
                        <input type="password" id="reg-password" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="reg-confirm-password">Confirm Password</label>
                        <input type="password" id="reg-confirm-password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Register</button>
                </form>
                <p style="text-align: center; margin-top: 15px;">
                    <a href="#" id="show-login">Already have an account? Login here</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Sample data for demonstration (simulating backend data)
        const sampleBooks = [
            {
                id: 1,
                title: "Introduction to Algorithms",
                author: "Thomas H. Cormen",
                isbn: "978-0262033848",
                edition: "3rd",
                department: "Computer Science",
                cover: "https://via.placeholder.com/200x300/3498db/ffffff?text=Algorithms",
                pdf: "sample.pdf",
                status: "available",
                downloads: 142
            },
            {
                id: 2,
                title: "Clean Code",
                author: "Robert C. Martin",
                isbn: "978-0132350884",
                edition: "1st",
                department: "Computer Science",
                cover: "https://via.placeholder.com/200x300/2ecc71/ffffff?text=Clean+Code",
                pdf: "sample.pdf",
                status: "available",
                downloads: 98
            },
            {
                id: 3,
                title: "The Great Gatsby",
                author: "F. Scott Fitzgerald",
                isbn: "978-0743273565",
                edition: "Reprint",
                department: "Literature",
                cover: "https://via.placeholder.com/200x300/e74c3c/ffffff?text=Gatsby",
                pdf: "sample.pdf",
                status: "borrowed",
                downloads: 76
            },
            {
                id: 4,
                title: "A Brief History of Time",
                author: "Stephen Hawking",
                isbn: "978-0553380163",
                edition: "Illustrated",
                department: "Physics",
                cover: "https://via.placeholder.com/200x300/9b59b6/ffffff?text=Time",
                pdf: "sample.pdf",
                status: "available",
                downloads: 120
            },
            {
                id: 5,
                title: "Calculus: Early Transcendentals",
                author: "James Stewart",
                isbn: "978-1285741550",
                edition: "8th",
                department: "Mathematics",
                cover: "https://via.placeholder.com/200x300/f39c12/ffffff?text=Calculus",
                pdf: "sample.pdf",
                status: "available",
                downloads: 85
            },
            {
                id: 6,
                title: "Molecular Biology of the Cell",
                author: "Bruce Alberts",
                isbn: "978-0815344322",
                edition: "6th",
                department: "Biology",
                cover: "https://via.placeholder.com/200x300/1abc9c/ffffff?text=Cell+Biology",
                pdf: "sample.pdf",
                status: "available",
                downloads: 64
            }
        ];

        const sampleUsers = [
            {
                id: 1,
                name: "John Doe",
                email: "student@example.com",
                role: "student",
                department: "Computer Science",
                age: 22,
                gender: "Male"
            },
            {
                id: 2,
                name: "Jane Smith",
                email: "librarian@example.com",
                role: "librarian",
                department: "",
                age: 35,
                gender: "Female"
            },
            {
                id: 3,
                name: "Admin User",
                email: "admin@example.com",
                role: "admin",
                department: "",
                age: 40,
                gender: "Male"
            }
        ];

        // Current user state
        let currentUser = null;
        let borrowedBooks = [];
        let downloadHistory = [];
        let borrowRequests = [];
        let allUsers = [...sampleUsers];

        // DOM Elements
        const pageSections = document.querySelectorAll('.page-section');
        const menuItems = document.querySelectorAll('.menu-item');
        const adminOnlyElements = document.querySelectorAll('.admin-only');
        const loginModal = document.getElementById('login-modal');
        const registerModal = document.getElementById('register-modal');
        const loginForm = document.getElementById('login-form');
        const registerForm = document.getElementById('register-form');
        const showRegisterLink = document.getElementById('show-register');
        const showLoginLink = document.getElementById('show-login');
        const logoutBtn = document.getElementById('logout-btn');
        const userNameSpan = document.getElementById('user-name');
        const userDepartmentSpan = document.getElementById('user-department');
        const userAvatar = document.getElementById('user-avatar');
        const recentBooksContainer = document.getElementById('recent-books');
        const popularBooksContainer = document.getElementById('popular-books');
        const allBooksContainer = document.getElementById('all-books');
        const userRoleSelect = document.getElementById('user-role');
        const studentFields = document.querySelectorAll('.student-fields');
        const closeModalButtons = document.querySelectorAll('.close-modal');

        // Initialize the application
        function initApp() {
            // Check if user is logged in (for demo purposes, we'll assume not)
            showLoginModal();
            
            // Set up event listeners
            setupEventListeners();
            
            // Load sample data
            loadSampleData();
        }

        // Set up event listeners
        function setupEventListeners() {
            // Navigation
            menuItems.forEach(item => {
                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    const page = item.getAttribute('data-page');
                    showPage(page);
                    
                    // Update active menu item
                    menuItems.forEach(i => i.classList.remove('active'));
                    item.classList.add('active');
                });
            });
            
            // Tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    const tabId = tab.getAttribute('data-tab');
                    const container = tab.closest('.tabs').parentElement;
                    
                    // Update active tab
                    tab.parentElement.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                    tab.classList.add('active');
                    
                    // Show corresponding content
                    container.querySelectorAll('.tab-content').forEach(content => {
                        content.classList.remove('active');
                    });
                    document.getElementById(`${tabId}-tab`).classList.add('active');
                });
            });
            
            // Login/Register forms
            loginForm.addEventListener('submit', handleLogin);
            registerForm.addEventListener('submit', handleRegister);
            showRegisterLink.addEventListener('click', (e) => {
                e.preventDefault();
                loginModal.style.display = 'none';
                registerModal.style.display = 'flex';
            });
            showLoginLink.addEventListener('click', (e) => {
                e.preventDefault();
                registerModal.style.display = 'none';
                loginModal.style.display = 'flex';
            });
            
            // Logout
            logoutBtn.addEventListener('click', handleLogout);
            
            // User role change (in add user form)
            userRoleSelect.addEventListener('change', function() {
                const isStudent = this.value === 'student';
                studentFields.forEach(field => {
                    field.style.display = isStudent ? 'block' : 'none';
                });
            });
            
            // Add book form
            document.getElementById('add-book-form').addEventListener('submit', handleAddBook);
            
            // Add user form
            document.getElementById('add-user-form').addEventListener('submit', handleAddUser);
            
            // Book search
            document.getElementById('book-search-btn').addEventListener('click', handleSearch);
            document.getElementById('book-search').addEventListener('keyup', function(e) {
                if (e.key === 'Enter') {
                    handleSearch();
                }
            });
            
            // Department filter
            document.getElementById('department-filter').addEventListener('change', handleSearch);
            
            // Close modals
            closeModalButtons.forEach(button => {
                button.addEventListener('click', () => {
                    loginModal.style.display = 'none';
                    registerModal.style.display = 'none';
                });
            });
        }

        // Show login modal
        function showLoginModal() {
            loginModal.style.display = 'flex';
            registerModal.style.display = 'none';
        }

        // Show specific page
        function showPage(pageId) {
            pageSections.forEach(section => {
                section.classList.remove('active');
            });
            document.getElementById(pageId).classList.add('active');
            
            // Load page-specific data
            if (pageId === 'dashboard') {
                loadDashboard();
            } else if (pageId === 'books') {
                loadBooks();
            } else if (pageId === 'my-books') {
                loadMyBooks();
            } else if (pageId === 'manage-books') {
                loadManageBooks();
            } else if (pageId === 'manage-users') {
                loadManageUsers();
            } else if (pageId === 'borrow-requests') {
                loadBorrowRequests();
            }
        }

        // Handle login
        function handleLogin(e) {
            e.preventDefault();
            const email = document.getElementById('login-email').value;
            const password = document.getElementById('login-password').value;
            const role = document.getElementById('login-role').value;
            
            // In a real app, this would be a server request
            const user = sampleUsers.find(u => u.email === email && u.role === role);
            
            if (user) {
                currentUser = user;
                updateUIForUser();
                loginModal.style.display = 'none';
                
                // Show success message
                alert(`Welcome back, ${user.name}!`);
            } else {
                alert('Invalid credentials. Please try again.');
            }
        }

        // Handle registration
        function handleRegister(e) {
            e.preventDefault();
            const fullName = document.getElementById('reg-fullname').value;
            const age = document.getElementById('reg-age').value;
            const gender = document.getElementById('reg-gender').value;
            const department = document.getElementById('reg-department').value;
            const email = document.getElementById('reg-email').value;
            const password = document.getElementById('reg-password').value;
            const confirmPassword = document.getElementById('reg-confirm-password').value;
            
            if (password !== confirmPassword) {
                alert('Passwords do not match.');
                return;
            }
            
            // In a real app, this would be sent to the server
            const newUser = {
                id: sampleUsers.length + 1,
                name: fullName,
                email: email,
                role: 'student',
                department: department,
                age: age,
                gender: gender
            };
            
            sampleUsers.push(newUser);
            allUsers.push(newUser);
            currentUser = newUser;
            updateUIForUser();
            registerModal.style.display = 'none';
            
            // Show success message
            alert('Registration successful! You are now logged in.');
        }

        // Handle logout
        function handleLogout() {
            currentUser = null;
            updateUIForUser();
            showLoginModal();
        }

        // Update UI based on current user
        function updateUIForUser() {
            if (currentUser) {
                userNameSpan.textContent = `${currentUser.name} (${currentUser.role.charAt(0).toUpperCase() + currentUser.role.slice(1)})`;
                userDepartmentSpan.textContent = currentUser.department || 'Administration';
                userAvatar.textContent = currentUser.name.split(' ').map(n => n[0]).join('');
                
                // Show/hide admin elements based on role
                const isAdmin = currentUser.role === 'admin' || currentUser.role === 'librarian';
                adminOnlyElements.forEach(el => {
                    el.style.display = isAdmin ? 'block' : 'none';
                });
                
                // Load user-specific data
                loadUserData();
            } else {
                userNameSpan.textContent = 'Guest';
                userDepartmentSpan.textContent = '';
                userAvatar.textContent = '?';
                adminOnlyElements.forEach(el => {
                    el.style.display = 'none';
                });
            }
            
            // Load books
            loadBooks();
        }

        // Load sample data
        function loadSampleData() {
            // Initialize with some sample borrowings and downloads
            borrowedBooks = [
                {
                    id: 1,
                    bookId: 3,
                    bookTitle: "The Great Gatsby",
                    bookAuthor: "F. Scott Fitzgerald",
                    borrowDate: "2023-10-15",
                    dueDate: "2023-10-29"
                }
            ];
            
            downloadHistory = [
                {
                    id: 1,
                    bookId: 1,
                    bookTitle: "Introduction to Algorithms",
                    bookAuthor: "Thomas H. Cormen",
                    downloadDate: "2023-10-10"
                },
                {
                    id: 2,
                    bookId: 2,
                    bookTitle: "Clean Code",
                    bookAuthor: "Robert C. Martin",
                    downloadDate: "2023-10-05"
                }
            ];
            
            borrowRequests = [
                {
                    id: 1,
                    bookId: 4,
                    bookTitle: "A Brief History of Time",
                    bookAuthor: "Stephen Hawking",
                    requestDate: "2023-10-18",
                    status: "pending"
                }
            ];
        }

        // Load dashboard data
        function loadDashboard() {
            // Update statistics
            document.getElementById('total-books').textContent = sampleBooks.length;
            document.getElementById('total-downloads').textContent = sampleBooks.reduce((sum, book) => sum + book.downloads, 0);
            document.getElementById('borrowed-books').textContent = borrowedBooks.length;
            document.getElementById('overdue-books').textContent = 0;
            
            // Load recent books
            recentBooksContainer.innerHTML = '';
            sampleBooks.slice(0, 4).forEach(book => {
                recentBooksContainer.appendChild(createBookCard(book));
            });
            
            // Load popular books
            popularBooksContainer.innerHTML = '';
            [...sampleBooks].sort((a, b) => b.downloads - a.downloads).slice(0, 4).forEach(book => {
                popularBooksContainer.appendChild(createBookCard(book));
            });
        }

        // Load books into the UI
        function loadBooks() {
            allBooksContainer.innerHTML = '';
            sampleBooks.forEach(book => {
                allBooksContainer.appendChild(createBookCard(book));
            });
        }

        // Create a book card element
        function createBookCard(book) {
            const card = document.createElement('div');
            card.className = 'book-card';
            card.innerHTML = `
                <div class="book-cover">
                    <img src="${book.cover}" alt="${book.title}">
                    <div class="book-badge">${book.department}</div>
                </div>
                <div class="book-info">
                    <div class="book-title">${book.title}</div>
                    <div class="book-author">${book.author}</div>
                    <div class="book-meta">
                        <span>${book.edition}</span>
                        <span>${book.downloads} downloads</span>
                    </div>
                    <div class="book-actions">
                        <button class="btn btn-primary btn-sm download-btn" data-id="${book.id}">
                            <i class="fas fa-download"></i> Download
                        </button>
                        <button class="btn btn-secondary btn-sm borrow-btn" data-id="${book.id}">
                            <i class="fas fa-exchange-alt"></i> Borrow
                        </button>
                    </div>
                </div>
            `;
            
            // Add event listeners to buttons
            card.querySelector('.download-btn').addEventListener('click', () => handleDownload(book));
            card.querySelector('.borrow-btn').addEventListener('click', () => handleBorrowRequest(book));
            
            return card;
        }

        // Handle book download
        function handleDownload(book) {
            if (!currentUser) {
                alert('Please log in to download books.');
                return;
            }
            
            // Add to download history
            downloadHistory.push({
                id: downloadHistory.length + 1,
                bookId: book.id,
                bookTitle: book.title,
                bookAuthor: book.author,
                downloadDate: new Date().toLocaleDateString()
            });
            
            // Update UI
            if (document.getElementById('downloads-tab').classList.contains('active')) {
                loadDownloadHistory();
            }
            
            // In a real app, this would trigger a file download
            alert(`Downloading "${book.title}" by ${book.author}`);
        }

        // Handle borrow request
        function handleBorrowRequest(book) {
            if (!currentUser) {
                alert('Please log in to borrow books.');
                return;
            }
            
            // Check if already borrowed
            if (borrowedBooks.some(b => b.bookId === book.id)) {
                alert('You have already borrowed this book.');
                return;
            }
            
            // Check if already requested
            if (borrowRequests.some(r => r.bookId === book.id && r.status === 'pending')) {
                alert('You have already requested to borrow this book.');
                return;
            }
            
            // Add to borrow requests
            const request = {
                id: borrowRequests.length + 1,
                bookId: book.id,
                bookTitle: book.title,
                bookAuthor: book.author,
                studentId: currentUser.id,
                studentName: currentUser.name,
                requestDate: new Date().toLocaleDateString(),
                status: 'pending'
            };
            
            borrowRequests.push(request);
            
            // Update UI
            if (document.getElementById('requests-tab').classList.contains('active')) {
                loadBorrowRequests();
            }
            
            alert(`Borrow request submitted for "${book.title}"`);
        }

        // Load user-specific data
        function loadUserData() {
            loadBorrowedBooks();
            loadDownloadHistory();
            loadBorrowRequests();
        }

        // Load borrowed books
        function loadBorrowedBooks() {
            const tableBody = document.querySelector('#borrowed-books-table tbody');
            tableBody.innerHTML = '';
            
            borrowedBooks.forEach(book => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${book.bookTitle}</td>
                    <td>${book.bookAuthor}</td>
                    <td>${book.borrowDate}</td>
                    <td>${book.dueDate}</td>
                    <td>
                        <button class="btn btn-success btn-sm return-btn" data-id="${book.bookId}">
                            <i class="fas fa-undo"></i> Return
                        </button>
                    </td>
                `;
                
                row.querySelector('.return-btn').addEventListener('click', () => handleReturnBook(book.id));
                tableBody.appendChild(row);
            });
        }

        // Load download history
        function loadDownloadHistory() {
            const tableBody = document.querySelector('#download-history-table tbody');
            tableBody.innerHTML = '';
            
            downloadHistory.forEach(item => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${item.bookTitle}</td>
                    <td>${item.bookAuthor}</td>
                    <td>${item.downloadDate}</td>
                    <td>
                        <button class="btn btn-primary btn-sm download-again-btn" data-id="${item.bookId}">
                            <i class="fas fa-download"></i> Download Again
                        </button>
                    </td>
                `;
                
                row.querySelector('.download-again-btn').addEventListener('click', () => {
                    const book = sampleBooks.find(b => b.id === item.bookId);
                    if (book) handleDownload(book);
                });
                tableBody.appendChild(row);
            });
        }

        // Load borrow requests
        function loadBorrowRequests() {
            const tableBody = document.querySelector('#borrow-requests-table tbody');
            tableBody.innerHTML = '';
            
            const userRequests = borrowRequests.filter(r => r.studentId === currentUser.id);
            
            userRequests.forEach(request => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${request.bookTitle}</td>
                    <td>${request.bookAuthor}</td>
                    <td>${request.requestDate}</td>
                    <td>
                        <span class="status-badge status-${request.status}">${request.status.charAt(0).toUpperCase() + request.status.slice(1)}</span>
                    </td>
                    <td>
                        ${request.status === 'pending' ? 
                            `<button class="btn btn-danger btn-sm cancel-request-btn" data-id="${request.id}">
                                <i class="fas fa-times"></i> Cancel
                            </button>` : 
                            ''
                        }
                    </td>
                `;
                
                if (request.status === 'pending') {
                    row.querySelector('.cancel-request-btn').addEventListener('click', () => handleCancelRequest(request.id));
                }
                tableBody.appendChild(row);
            });
        }

        // Load my books page
        function loadMyBooks() {
            loadBorrowedBooks();
            loadDownloadHistory();
            loadBorrowRequests();
        }

        // Load manage books page
        function loadManageBooks() {
            const tableBody = document.querySelector('#admin-book-list tbody');
            tableBody.innerHTML = '';
            
            sampleBooks.forEach(book => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${book.title}</td>
                    <td>${book.author}</td>
                    <td>${book.isbn}</td>
                    <td>${book.department}</td>
                    <td>
                        <span class="status-badge status-${book.status}">${book.status.charAt(0).toUpperCase() + book.status.slice(1)}</span>
                    </td>
                    <td>
                        <button class="btn btn-primary btn-sm edit-book-btn" data-id="${book.id}">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn btn-danger btn-sm delete-book-btn" data-id="${book.id}">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </td>
                `;
                
                row.querySelector('.edit-book-btn').addEventListener('click', () => handleEditBook(book.id));
                row.querySelector('.delete-book-btn').addEventListener('click', () => handleDeleteBook(book.id));
                tableBody.appendChild(row);
            });
        }

        // Load manage users page
        function loadManageUsers() {
            const tableBody = document.querySelector('#admin-user-list tbody');
            tableBody.innerHTML = '';
            
            allUsers.forEach(user => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${user.name}</td>
                    <td>${user.email}</td>
                    <td>${user.role.charAt(0).toUpperCase() + user.role.slice(1)}</td>
                    <td>${user.department || '-'}</td>
                    <td>
                        <button class="btn btn-primary btn-sm edit-user-btn" data-id="${user.id}">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                        <button class="btn btn-danger btn-sm delete-user-btn" data-id="${user.id}">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                        <button class="btn btn-secondary btn-sm reset-password-btn" data-id="${user.id}">
                            <i class="fas fa-key"></i> Reset
                        </button>
                    </td>
                `;
                
                row.querySelector('.edit-user-btn').addEventListener('click', () => handleEditUser(user.id));
                row.querySelector('.delete-user-btn').addEventListener('click', () => handleDeleteUser(user.id));
                row.querySelector('.reset-password-btn').addEventListener('click', () => handleResetPassword(user.id));
                tableBody.appendChild(row);
            });
        }

        // Load borrow requests page
        function loadBorrowRequests() {
            // Pending requests
            const pendingTableBody = document.querySelector('#pending-requests-table tbody');
            pendingTableBody.innerHTML = '';
            
            const pendingRequests = borrowRequests.filter(r => r.status === 'pending');
            
            pendingRequests.forEach(request => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${request.studentName}</td>
                    <td>${request.bookTitle}</td>
                    <td>${request.bookAuthor}</td>
                    <td>${request.requestDate}</td>
                    <td>
                        <button class="btn btn-success btn-sm approve-request-btn" data-id="${request.id}">
                            <i class="fas fa-check"></i> Approve
                        </button>
                        <button class="btn btn-danger btn-sm reject-request-btn" data-id="${request.id}">
                            <i class="fas fa-times"></i> Reject
                        </button>
                    </td>
                `;
                
                row.querySelector('.approve-request-btn').addEventListener('click', () => handleApproveRequest(request.id));
                row.querySelector('.reject-request-btn').addEventListener('click', () => handleRejectRequest(request.id));
                pendingTableBody.appendChild(row);
            });
            
            // All requests
            const allTableBody = document.querySelector('#all-requests-table tbody');
            allTableBody.innerHTML = '';
            
            borrowRequests.forEach(request => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${request.studentName}</td>
                    <td>${request.bookTitle}</td>
                    <td>${request.bookAuthor}</td>
                    <td>${request.requestDate}</td>
                    <td>
                        <span class="status-badge status-${request.status}">${request.status.charAt(0).toUpperCase() + request.status.slice(1)}</span>
                    </td>
                    <td>
                        ${request.status === 'pending' ? 
                            `<button class="btn btn-success btn-sm approve-request-btn" data-id="${request.id}">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button class="btn btn-danger btn-sm reject-request-btn" data-id="${request.id}">
                                <i class="fas fa-times"></i> Reject
                            </button>` : 
                            ''
                        }
                    </td>
                `;
                
                if (request.status === 'pending') {
                    row.querySelector('.approve-request-btn').addEventListener('click', () => handleApproveRequest(request.id));
                    row.querySelector('.reject-request-btn').addEventListener('click', () => handleRejectRequest(request.id));
                }
                allTableBody.appendChild(row);
            });
        }

        // Handle return book
        function handleReturnBook(bookId) {
            borrowedBooks = borrowedBooks.filter(b => b.id !== bookId);
            loadBorrowedBooks();
            alert('Book returned successfully.');
        }

        // Handle cancel borrow request
        function handleCancelRequest(requestId) {
            borrowRequests = borrowRequests.filter(r => r.id !== requestId);
            loadBorrowRequests();
            alert('Borrow request cancelled.');
        }

        // Handle add book (admin/librarian)
        function handleAddBook(e) {
            e.preventDefault();
            const title = document.getElementById('book-title').value;
            const author = document.getElementById('book-author').value;
            const isbn = document.getElementById('book-isbn').value;
            const edition = document.getElementById('book-edition').value;
            const department = document.getElementById('book-department').value;
            
            // In a real app, we would upload the files to a server
            const newBook = {
                id: sampleBooks.length + 1,
                title: title,
                author: author,
                isbn: isbn,
                edition: edition,
                department: department,
                cover: "https://via.placeholder.com/200x300/95a5a6/ffffff?text=New+Book",
                pdf: "new-book.pdf",
                status: "available",
                downloads: 0
            };
            
            sampleBooks.push(newBook);
            loadBooks();
            loadManageBooks();
            
            // Reset form
            document.getElementById('add-book-form').reset();
            
            alert('Book added successfully!');
        }

        // Handle add user (admin)
        function handleAddUser(e) {
            e.preventDefault();
            const role = document.getElementById('user-role').value;
            const fullName = document.getElementById('user-fullname').value;
            const email = document.getElementById('user-email').value;
            const password = document.getElementById('user-password').value;
            
            let age = '', gender = '', department = '';
            if (role === 'student') {
                age = document.getElementById('user-age').value;
                gender = document.getElementById('user-gender').value;
                department = document.getElementById('user-department').value;
            }
            
            // In a real app, this would be sent to the server
            const newUser = {
                id: allUsers.length + 1,
                name: fullName,
                email: email,
                role: role,
                department: department,
                age: age,
                gender: gender
            };
            
            allUsers.push(newUser);
            loadManageUsers();
            
            // Reset form
            document.getElementById('add-user-form').reset();
            
            alert('User added successfully!');
        }

        // Handle book search
        function handleSearch() {
            const query = document.getElementById('book-search').value.toLowerCase();
            const department = document.getElementById('department-filter').value;
            
            let filteredBooks = sampleBooks;
            
            if (query) {
                filteredBooks = filteredBooks.filter(book => 
                    book.title.toLowerCase().includes(query) || 
                    book.author.toLowerCase().includes(query)
                );
            }
            
            if (department) {
                filteredBooks = filteredBooks.filter(book => book.department === department);
            }
            
            allBooksContainer.innerHTML = '';
            filteredBooks.forEach(book => {
                allBooksContainer.appendChild(createBookCard(book));
            });
        }

        // Handle approve borrow request (admin/librarian)
        function handleApproveRequest(requestId) {
            const request = borrowRequests.find(r => r.id === requestId);
            if (request) {
                request.status = 'approved';
                
                // Add to borrowed books
                const dueDate = new Date();
                dueDate.setDate(dueDate.getDate() + 14); // 2 weeks from now
                
                borrowedBooks.push({
                    id: borrowedBooks.length + 1,
                    bookId: request.bookId,
                    bookTitle: request.bookTitle,
                    bookAuthor: request.bookAuthor,
                    borrowDate: new Date().toLocaleDateString(),
                    dueDate: dueDate.toLocaleDateString()
                });
                
                // Update book status
                const book = sampleBooks.find(b => b.id === request.bookId);
                if (book) {
                    book.status = 'borrowed';
                }
                
                loadBorrowRequests();
                loadBorrowedBooks();
                
                alert(`Borrow request for "${request.bookTitle}" approved.`);
            }
        }

        // Handle reject borrow request (admin/librarian)
        function handleRejectRequest(requestId) {
            const request = borrowRequests.find(r => r.id === requestId);
            if (request) {
                request.status = 'rejected';
                loadBorrowRequests();
                
                alert(`Borrow request for "${request.bookTitle}" rejected.`);
            }
        }

        // Handle edit book (admin/librarian)
        function handleEditBook(bookId) {
            alert(`Edit book with ID: ${bookId} - This would open an edit form in a real application.`);
        }

        // Handle delete book (admin/librarian)
        function handleDeleteBook(bookId) {
            if (confirm('Are you sure you want to delete this book?')) {
                const index = sampleBooks.findIndex(b => b.id === bookId);
                if (index !== -1) {
                    sampleBooks.splice(index, 1);
                    loadBooks();
                    loadManageBooks();
                    alert('Book deleted successfully.');
                }
            }
        }

        // Handle edit user (admin)
        function handleEditUser(userId) {
            alert(`Edit user with ID: ${userId} - This would open an edit form in a real application.`);
        }

        // Handle delete user (admin)
        function handleDeleteUser(userId) {
            if (userId === currentUser.id) {
                alert('You cannot delete your own account.');
                return;
            }
            
            if (confirm('Are you sure you want to delete this user?')) {
                const index = allUsers.findIndex(u => u.id === userId);
                if (index !== -1) {
                    allUsers.splice(index, 1);
                    loadManageUsers();
                    alert('User deleted successfully.');
                }
            }
        }

        // Handle reset password (admin)
        function handleResetPassword(userId) {
            alert(`Reset password for user with ID: ${userId} - This would send a password reset email in a real application.`);
        }

        // Initialize the app when the DOM is loaded
        document.addEventListener('DOMContentLoaded', initApp);
    </script>
</body>
</html>