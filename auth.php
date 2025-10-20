<?php
require_once 'config.php';

class Auth {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function register($data) {
        // Validate required fields
        $required = ['full_name', 'email', 'password', 'department', 'age', 'sex'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                jsonResponse(false, "Field $field is required");
            }
        }
        
        // Check if email already exists
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$data['email']]);
        if ($stmt->fetch()) {
            jsonResponse(false, "Email already registered");
        }
        
        // Generate student ID
        $stud_id = 'STU' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Hash password
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Insert user
        $stmt = $this->pdo->prepare("INSERT INTO users (stud_id, full_name, age, sex, department, email, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt->execute([$stud_id, $data['full_name'], $data['age'], $data['sex'], $data['department'], $data['email'], $hashedPassword])) {
            jsonResponse(true, 'Registration successful');
        } else {
            jsonResponse(false, 'Registration failed');
        }
    }
    
    public function login($email, $password) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            // Set session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_department'] = $user['department'];
            
            jsonResponse(true, 'Login successful', [
                'id' => $user['id'],
                'name' => $user['full_name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'department' => $user['department']
            ]);
        } else {
            jsonResponse(false, 'Invalid email or password');
        }
    }
    
    public function logout() {
        session_destroy();
        jsonResponse(true, 'Logged out successfully');
    }
    
    public function getCurrentUser() {
        if (!isLoggedIn()) {
            return null;
        }
        
        $stmt = $this->pdo->prepare("SELECT id, stud_id, full_name, email, role, department FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}

// Handle authentication requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $auth = new Auth($pdo);
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'register':
            $auth->register($_POST);
            break;
            
        case 'login':
            if (empty($_POST['email']) || empty($_POST['password'])) {
                jsonResponse(false, 'Email and password are required');
            }
            $auth->login($_POST['email'], $_POST['password']);
            break;
            
        case 'logout':
            $auth->logout();
            break;
            
        case 'get_current_user':
            $user = $auth->getCurrentUser();
            jsonResponse(true, '', $user);
            break;
            
        default:
            jsonResponse(false, 'Invalid action');
    }
}
?>