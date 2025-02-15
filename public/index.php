<?php
session_start();

// Load environment variables
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

$coreFiles = glob('../app/Core/*.php');
foreach ($coreFiles as $file) {
    require_once $file;
}

$modelFiles = glob('../app/Models/*.php');
foreach ($modelFiles as $file) {
    require_once $file;
}

$controllerFiles = glob('../app/Controllers/*.php');
foreach ($controllerFiles as $file) {
    require_once $file;
}

// Include middleware
require_once '../app/Middleware/AuthMiddleware.php';

// Initialize the router
$router = new App\Core\Router();

// Home route
    $router->addRoute('GET', '/', 'HomeController', 'index');
    $router->addRoute('GET', '/home', 'HomeController', 'index');
    $router->addRoute('GET', '/home/index', 'HomeController', 'index');
    $router->addRoute('GET', '/info/loantype', 'InfoController', 'showLoanTypes');

// About routes
    $router->addRoute('GET', '/about/vision', 'HomeController', 'showVision');
    $router->addRoute('GET', '/about/history', 'HomeController', 'showHistory');
    $router->addRoute('GET', '/about/facts', 'HomeController', 'showFacts');
    $router->addRoute('GET', '/about/info/loantype', 'InfoController', 'showLoanTypes');

// Guest routes
    $router->addRoute('GET', '/guest/create', 'GuestController', 'create'); // For new member registration
    $router->addRoute('POST', '/guest/store', 'GuestController', 'store'); // To store new pending member

// Auth routes
    $router->addRoute('GET', '/auth/login', 'AuthController', 'showLogin');
    $router->addRoute('POST', '/auth/login', 'AuthController', 'login');
    $router->addRoute('GET', '/auth/register', 'AuthController', 'showRegister');
    $router->addRoute('POST', '/auth/register', 'AuthController', 'register');
    $router->addRoute('GET', '/auth/logout', 'AuthController', 'logout');
    $router->addRoute('GET', '/auth/setup-password', 'AuthController', 'showSetupPassword');
    $router->addRoute('POST', '/auth/setup-password', 'AuthController', 'setupPassword');

// Admin basic routes --login, register, logout
    $router->addRoute('GET', '/admin', 'AdminController', 'index');
    $router->addRoute('GET', '/admin/login', 'AdminController', 'login');
    $router->addRoute('GET', '/admin/member_list', 'AdminController', 'memberList');
    $router->addRoute('POST', '/admin/uploadReport', 'AdminController', 'uploadReport');
    $router->addRoute('GET', '/admin/downloadReport/{id}', 'AdminController', 'downloadReport');
    $router->addRoute('GET', '/admin/deleteReport/{id}', 'AdminController', 'deleteReport');
    $router->addRoute('POST', '/admin/update-interest-rates', 'AdminController', 'updateInterestRates'); 
    $router->addRoute('GET', '/admin/resignations', 'AdminController', 'showResignations');
    $router->addRoute('POST', '/admin/resignations/approve', 'AdminController', 'approveResignation');


    // Admin routes --approve, reject, edit, viewMember
        $router->addRoute('GET', '/admin/approve/{id}', 'AdminController', 'approve');
        $router->addRoute('GET', '/admin/reject/{id}', 'AdminController', 'reject');
        $router->addRoute('GET', '/admin/edit/{id}', 'AdminController', 'edit');
        $router->addRoute('GET', '/admin/view/{id}', 'AdminController', 'viewMember');
        $router->addRoute('GET', '/admin/export-pdf', 'AdminController', 'exportPdf');
        $router->addRoute('GET', '/admin/export-excel', 'AdminController', 'exportExcel');
        $router->addRoute('GET', '/admin/add-admin', 'AdminController', 'showAddAdmin');
        $router->addRoute('POST', '/admin/store-admin', 'AdminController', 'storeAdmin');
        $router->addRoute('GET', '/admin/delete-admin/{id}', 'AdminController', 'deleteAdmin');

    
    // Admin routes --annual reports
        // $router->addRoute('GET', '/admin/annual-reports', 'AnnualReportController', 'index');
        // $router->addRoute('GET', '/admin/annual-reports/upload', 'AnnualReportController', 'upload');
        // $router->addRoute('POST', '/admin/annual-reports/upload', 'AnnualReportController', 'upload');
        // $router->addRoute('GET', '/admin/annual-reports/download/{id}', 'AnnualReportController', 'download');
        // $router->addRoute('POST', '/admin/annual-reports/delete/{id}', 'AnnualReportController', 'delete');

// User Dashboard Routes
    $router->addRoute('GET', '/users', 'UserController', 'dashboard');
    $router->addRoute('GET', '/users/profile', 'UserController', 'profile');
    $router->addRoute('POST', '/users/profile/update', 'UserController', 'update');
    $router->addRoute('GET', '/users/resign', 'UserController', 'showResignForm');
    $router->addRoute('POST', '/users/resign/submit', 'UserController', 'submitResignation');
    $router->addRoute('GET', '/users/dashboard', 'UserController', 'dashboard');

    // User routes -- Savings Routes
        $router->addRoute('GET', '/users/savings/verify-member/{id}', 'SavingController', 'verifyMember');
        $router->addRoute('GET', '/users/savings/page', 'SavingController', 'savingsDashboard');
        $router->addRoute('GET', '/users/savings', 'SavingController', 'savingsDashboard'); // Alias route
        $router->addRoute('GET', '/users/savings/deposit', 'SavingController', 'showDepositForm');
        $router->addRoute('POST', '/users/savings/deposit', 'SavingController', 'makeDeposit');
        $router->addRoute('GET', '/users/savings/transfer', 'SavingController', 'showTransferForm');
        //$router->addRoute('POST', '/users/savings/transfer', 'SavingController', 'makeTransfer');
        $router->addRoute('GET', '/users/savings/transfer', 'SavingController', 'transferPage');
        $router->addRoute('POST', '/users/savings/transfer/make', 'SavingController', 'makeTransfer');
        $router->addRoute('POST', '/users/savings/goals', 'SavingController', 'createSavingsGoal');

        // User routes --Receipts
        $router->addRoute('GET', '/payment/receipt/{referenceNo}', 'SavingController', 'showReceipt');

        // User routes -- Payment
        $router->addRoute('POST', '/payment/process', 'PaymentController', 'processPayment');
        $router->addRoute('GET', '/payment/simulate/{provider}', 'PaymentController', 'showSimulation');
        $router->addRoute('POST', '/payment/callback', 'PaymentController', 'handleCallback');
        $router->addRoute('GET', '/users/savings/receipt/{referenceNo}', 'SavingController', 'showReceipt');
        
        // User routes -- Savings Goal
        $router->addRoute('GET', '/users/savings/goals/edit/{id}', 'SavingController', 'editGoal');
        $router->addRoute('POST', '/users/savings/goals/update/{id}', 'SavingController', 'updateGoal');
        $router->addRoute('GET', '/users/savings/goals/create', 'SavingController', 'createGoal');
        $router->addRoute('POST', '/users/savings/goals/store', 'SavingController', 'storeGoal');
        $router->addRoute('POST', '/users/savings/goals/delete/{id}', 'SavingController', 'deleteGoal');
        
        // User routes -- Savings Recurring
        $router->addRoute('GET', '/users/savings/recurring/edit', 'SavingController', 'editRecurringPayment');
        // $router->addRoute('POST', '/users/savings/recurring/update', 'SavingController', 'updateRecurringPayment');
        $router->addRoute('POST', '/users/savings/recurring/store', 'SavingController', 'storeRecurringPayment');
        $router->addRoute('GET', '/users/savings/recurring', 'SavingController', 'showRecurring');
        $router->addRoute('POST', '/users/savings/recurring/update/{id}', 'SavingController', 'updateRecurring');
        
        // User routes --Loans
        $router->addRoute('GET', '/users/loans/request', 'LoanController', 'showRequest');
        $router->addRoute('POST', '/users/loans/submitRequest', 'LoanController', 'submitRequest');
        $router->addRoute('GET', '/users/loans/status', 'LoanController', 'showStatus');
        $router->addRoute('GET', '/users/loans/details/{id}', 'LoanController', 'showDetails');

        // User routes --Statements
        $router->addRoute('GET', '/users/statements', 'StatementController', 'index');
        $router->addRoute('GET', '/users/statements/download', 'StatementController', 'download');
        $router->addRoute('GET', '/users/statements/savings', 'StatementController', 'savings');
        $router->addRoute('GET', '/users/statements/loans', 'StatementController', 'loans');
        $router->addRoute('POST', '/users/statements/notifications', 'StatementController', 'notifications');
        $router->addRoute('GET', '/users/resigned', 'UserController', 'resigned');

        // User routes --Loan Info
        $router->addRoute('GET', '/users/info/loantype', 'InfoController', 'showLoanTypes');
        

// Director routes
    $router->addRoute('GET', '/director', 'DirectorController', 'dashboard');
    $router->addRoute('GET', '/director/add', 'DirectorController', 'showAddDirector');
    $router->addRoute('POST', '/director/store', 'DirectorController', 'store');
    $router->addRoute('GET', '/director/loans', 'DirectorController', 'showLoans');
    $router->addRoute('POST', '/director/loans/update-status', 'DirectorController', 'updateLoanStatus');

// Guest routes
    $router->addRoute('GET', '/guest/check-status', 'GuestController', 'checkStatusPage');
    $router->addRoute('POST', '/guest/check-status', 'GuestController', 'checkStatus');

// $router->addRoute('GET', '/users/savings/goals/{id}/edit', 'SavingController', 'editSavingsGoal');
// $router->addRoute('POST', '/users/savings/goals/{id}/update', 'SavingController', 'updateSavingsGoal');
// $router->addRoute('POST', '/users/savings/goals/{id}/delete', 'SavingController', 'deleteSavingsGoal');

$router->addRoute('GET', '/users/fees/initial', 'UserFeeController', 'showInitialFees');
$router->addRoute('POST', '/users/fees/confirm', 'UserFeeController', 'confirmPayment');
$router->addRoute('GET', '/users/fees/success', 'UserFeeController', 'showSuccess');
$router->addRoute('GET', '/auth/setup-password', 'AuthController', 'showSetupPassword');
$router->addRoute('POST', '/auth/setup-password', 'AuthController', 'setupPassword'); 
$router->addRoute('GET', '/users/reactivate', 'UserController', 'showReactivateForm');
$router->addRoute('POST', '/users/reactivate/submit', 'UserController', 'submitReactivation');
$router->addRoute('GET', '/admin/edit-profile', 'AdminController', 'showEditProfile');
$router->addRoute('POST', '/admin/update-profile', 'AdminController', 'updateProfile');
$router->addRoute('GET', '/admin/edit-admin/{id}', 'AdminController', 'showEditAdmin');
$router->addRoute('POST', '/admin/update-admin/{id}', 'AdminController', 'updateAdminById');
$router->addRoute('GET', '/admin/edit-director/{id}', 'AdminController', 'showEditDirector');
$router->addRoute('POST', '/admin/update-director/{id}', 'AdminController', 'updateDirector');

// Get current URI and HTTP method
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

// Dispatch the route
$router->dispatch();    