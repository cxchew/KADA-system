<?php
namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Database;
use App\Models\Admin;
use PDO;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use App\Models\AnnualReport;

class AdminController extends BaseController {
    private $admin;
    private $annualReport;

    public function __construct()
    {
        $this->admin = new Admin();
        $this->annualReport = new AnnualReport();
    }

    public function index()
    {
        try {
            if (!isset($_SESSION['admin_id'])) {
                error_log('No admin_id in session');
                throw new \Exception('Sila log masuk sebagai admin');
            }

            // Get current admin details
            $currentAdmin = $this->admin->getAdminById($_SESSION['admin_id']);
            
            $admin = new Admin();
            $allMembers = $admin->getAllMembers();
            $reports = $this->annualReport->getAllReports();
            $interestRates = $this->admin->getInterestRates();
            $loanStats = $this->admin->getLoanStatistics();
            $resignations = $this->admin->getPendingResignations();
            $directors = $this->admin->getDirectors();
            $admins = $this->admin->getAllAdmins();
            
            $this->view('admin/index', [
                'members' => $allMembers,
                'annual_reports' => $reports,
                'stats' => [
                    'total' => count($allMembers),
                    'pending' => count(array_filter($allMembers, fn($m) => $m['status'] === 'Pending')),
                    'active' => count(array_filter($allMembers, fn($m) => $m['status'] === 'Active')),
                    'rejected' => count(array_filter($allMembers, fn($m) => $m['status'] === 'Rejected')),
                    'resigned' => count(array_filter($allMembers, fn($m) => $m['status'] === 'Resigned')),
                    'loans' => $loanStats['total_loans'] ?? 0,
                    'loan_amount' => $loanStats['total_amount'] ?? 0
                ],
                'interestRates' => $interestRates,
                'resignations' => $resignations,
                'directors' => $directors,
                'admins' => $admins,
                'currentAdmin' => $currentAdmin
            ]);
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: /auth/login');
            exit;
        }
    }

    public function viewMember($id)
    {
        $admin = new Admin();
        $member = $admin->getUserById($id);
        $this->view('admin/view', ['member' => $member]);
    }

    private function getMemberAccountDetails($id)
    {
        try {
            $sql = "SELECT * FROM savings_accounts WHERE member_id = :id";
            $stmt = $this->admin->getConnection()->prepare($sql);
            $stmt->execute([':id' => $id]);
            return $stmt->fetchAll(PDO::FETCH_OBJ);
        } catch (\PDOException $e) {
            error_log('Error getting account details: ' . $e->getMessage());
            return [];
        }
    }

    private function getMemberSavingsInfo($id)
    {
        try {
            $sql = "SELECT 
                    SUM(current_amount) as total_savings,
                    COUNT(*) as account_count
                    FROM savings_accounts 
                    WHERE member_id = :id";
            $stmt = $this->admin->getConnection()->prepare($sql);
            $stmt->execute([':id' => $id]);
            return $stmt->fetch(PDO::FETCH_OBJ);
        } catch (\PDOException $e) {
            error_log('Error getting savings info: ' . $e->getMessage());
            return null;
        }
    }

    private function getMemberLoanInfo($id)
    {
        try {
            $sql = "SELECT 
                    COUNT(*) as total_loans,
                    SUM(amount) as total_amount,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_loans
                    FROM loans 
                    WHERE member_id = :id";
            $stmt = $this->admin->getConnection()->prepare($sql);
            $stmt->execute([':id' => $id]);
            return $stmt->fetch(PDO::FETCH_OBJ);
        } catch (\PDOException $e) {
            error_log('Error getting loan info: ' . $e->getMessage());
            return null;
        }
    }

    public function approve($id)
    {
        try {
            $admin = new Admin();
            $member = $admin->getMemberById($id);

            if ($member['member_type'] === 'Rejected') {
                if ($admin->migrateFromRejected($id)) {
                    $_SESSION['success'] = "Ahli telah berjaya dipindahkan ke senarai ahli aktif";
                } else {
                    throw new \Exception("Gagal memindahkan ahli");
                }
            } else {
                $admin->updateStatus($id, 'Lulus');
                $_SESSION['success'] = "Status telah berjaya dikemaskini kepada Lulus";
            }
            
            header('Location: /admin/member_list');
            exit();
        } catch (\Exception $e) {
            $_SESSION['error'] = "Gagal mengemaskini status: " . $e->getMessage();
            header('Location: /admin/member_list');
            exit();
        }
    }

    public function reject($id)
    {
        try {
            $admin = new Admin();
            if ($admin->reject($id)) {
                $_SESSION['success'] = "Permohonan telah berjaya ditolak dan dipindahkan ke senarai rejected";
            } else {
                throw new \Exception("Gagal menolak permohonan");
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: /admin');
        exit();
    }

    public function rejectMember($id)
    {
        try {
            $admin = new Admin();
            $admin->updateStatus($id, 'rejected');
            $_SESSION['success'] = "Permohonan telah berjaya ditolak dan dipindahkan ke senarai rejected";
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }
        header('Location: /admin');
        exit();
    }

    // public function edit($id)
    // {
    //     $admin = $this->admin->find($id);

    //     $this->view('admin/edit', compact('admin'));
    // }

    // public function update($id)
    // {
    //     $this->user->update($id, $_POST);
    //     header('Location: /');
    // }

    // private function checkAuth()
    // {
    //     if (!isset($_SESSION['admin_id'])) {
    //         header('Location: /auth/login');
    //         exit();
    //     }
    // }

    public function exportPDF() {
        try {
            ob_start();
            
            $members = $this->admin->getAllMembers();
            
            require_once dirname(__DIR__, 2) . '/vendor/tecnickcom/tcpdf/tcpdf.php';
            
            // Create PDF instance with landscape orientation
            $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
            
            // Set document information
            $pdf->SetCreator('KADA System');
            $pdf->SetTitle('Senarai Ahli KADA');
            
            // Remove default header/footer
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            
            // Set margins
            $pdf->SetMargins(10, 10, 10);
            
            // Disable auto page breaks
            $pdf->SetAutoPageBreak(TRUE, 15);
            
            // Add a page
            $pdf->AddPage();
            
            // Add logo
            $logoPath = dirname(__DIR__, 2) . '/public/img/logo-kada.png';
            if (file_exists($logoPath)) {
                $pdf->Image($logoPath, 10, 10, 25);
            }
            
            // Add title with spacing after logo
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->SetXY(40, 15);
            $pdf->Cell(0, 10, 'SENARAI AHLI KADA', 0, 1, 'L');
            
            // Set font for table
            $pdf->SetFont('helvetica', '', 9);
            
            // Move to position for table
            $pdf->SetXY(10, 35);
            
            // Table header
            $header = array('No.', 'Nama', 'No. K/P', 'Jantina', 'Jawatan', 'Gaji', 'Status');
            
            // Calculate column widths (total = 277mm for landscape A4)
            $widths = array(12, 75, 35, 20, 65, 30, 30);
            
            // Colors for header
            $pdf->SetFillColor(52, 58, 64);
            $pdf->SetTextColor(255, 255, 255);
            
            // Header row
            $pdf->SetFont('helvetica', 'B', 9);
            for($i = 0; $i < count($header); $i++) {
                $pdf->Cell($widths[$i], 8, $header[$i], 1, 0, 'C', true);
            }
            $pdf->Ln();
            
            // Reset text color for data
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('helvetica', '', 9);
            
            $counter = 1;
            foreach ($members as $member) {
                // Check if we need a new page
                if ($pdf->GetY() > 180) {
                    $pdf->AddPage();
                    // Reprint header
                    $pdf->SetFillColor(52, 58, 64);
                    $pdf->SetTextColor(255, 255, 255);
                    $pdf->SetFont('helvetica', 'B', 9);
                    for($i = 0; $i < count($header); $i++) {
                        $pdf->Cell($widths[$i], 8, $header[$i], 1, 0, 'C', true);
                    }
                    $pdf->Ln();
                    $pdf->SetTextColor(0, 0, 0);
                    $pdf->SetFont('helvetica', '', 9);
                }
                
                $pdf->Cell($widths[0], 7, $counter++, 1, 0, 'C');
                $pdf->Cell($widths[1], 7, $member['name'], 1, 0, 'L');
                $pdf->Cell($widths[2], 7, $member['ic_no'], 1, 0, 'L');
                $pdf->Cell($widths[3], 7, $member['gender'], 1, 0, 'C');
                $pdf->Cell($widths[4], 7, $member['position'], 1, 0, 'L');
                $pdf->Cell($widths[5], 7, 'RM ' . number_format($member['monthly_salary'], 2), 1, 0, 'R');
                $pdf->Cell($widths[6], 7, $member['status'], 1, 0, 'C');
                $pdf->Ln();
            }
            
            // Get the last page number
            $lastPage = $pdf->getPage();
            
            // For each page, add the generation date at the bottom
            for($i = 1; $i <= $lastPage; $i++) {
                $pdf->setPage($i);
                // Set position at bottom of current page
                $pdf->SetY(-25);
                $pdf->SetFont('helvetica', 'I', 8);
                $pdf->Cell(0, 10, 'Dijana pada: ' . date('d/m/Y H:i:s'), 0, 0, 'R');
            }
            
            // Clear any previous output
            ob_clean();
            
            // Output PDF
            $pdf->Output('senarai_ahli.pdf', 'D');
            exit();
            
        } catch (\Exception $e) {
            ob_end_clean();
            $_SESSION['error'] = "Error generating PDF: " . $e->getMessage();
            header('Location: /admin/member_list');
            exit();
        }
    }

    public function exportExcel() {
        try {
            // Start output buffering
            ob_start();
            
            // Require the autoloader
            require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
            
            // Get members data
            $members = $this->admin->getAllMembers();
            
            // Create new Spreadsheet object with full namespace reference
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Set document properties
            $spreadsheet->getProperties()
                ->setCreator('KADA System')
                ->setTitle('Senarai Ahli KADA');
            
            // Set headers
            $headers = ['No.', 'Nama', 'No. K/P', 'Jantina', 'Jawatan', 'Gaji', 'Status'];
            $columns = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
            
            // Style for headers
            $headerStyle = [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '343A40'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ];
            
            // Set column widths
            $sheet->getColumnDimension('A')->setWidth(8);  // No.
            $sheet->getColumnDimension('B')->setWidth(35); // Nama
            $sheet->getColumnDimension('C')->setWidth(20); // No. K/P
            $sheet->getColumnDimension('D')->setWidth(15); // Jantina
            $sheet->getColumnDimension('E')->setWidth(25); // Jawatan
            $sheet->getColumnDimension('F')->setWidth(15); // Gaji
            $sheet->getColumnDimension('G')->setWidth(15); // Status
            
            // Add headers
            foreach ($headers as $index => $header) {
                $sheet->setCellValue($columns[$index] . '1', $header);
            }
            
            // Style the header row
            $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);
            
            // Add data
            $row = 2;
            $counter = 1;
            foreach ($members as $member) {
                $sheet->setCellValue('A' . $row, $counter++);
                $sheet->setCellValue('B' . $row, $member['name']);
                $sheet->setCellValue('C' . $row, $member['ic_no']);
                $sheet->setCellValue('D' . $row, $member['gender']);
                $sheet->setCellValue('E' . $row, $member['position']);
                $sheet->setCellValue('F' . $row, 'RM ' . number_format($member['monthly_salary'], 2));
                $sheet->setCellValue('G' . $row, $member['status']);
                
                // Center align the number and status columns
                $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('G' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                
                $row++;
            }
            
            // Create Excel file
            $writer = new Xlsx($spreadsheet);
            
            // Clear any previous output
            ob_clean();
            
            // Set headers for download
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="senarai_ahli.xlsx"');
            header('Cache-Control: max-age=0');
            
            // Save to PHP output
            $writer->save('php://output');
            exit();
            
        } catch (\Exception $e) {
            ob_end_clean();
            $_SESSION['error'] = "Error generating Excel file: " . $e->getMessage();
            header('Location: /admin/member_list');
            exit();
        }
    }

    public function updateStatus()
    {
        try {
            $id = $_POST['id'] ?? null;
            $status = $_POST['status'] ?? null;
            
            if (!$id || !$status) {
                throw new \Exception("ID and status are required");
            }

            $result = $this->admin->updateStatus($id, $status);
            if ($result) {
                $_SESSION['success'] = "Status telah berjaya dikemaskini";
            }
            
            header('Location: /admin');
            exit;
            
        } catch (\Exception $e) {
            $_SESSION['error'] = "Gagal mengemaskini status: " . $e->getMessage();
            header('Location: /admin');
            exit;
        }
    }

    public function memberList() {
        try {
            $admin = new Admin();
            $allMembers = $admin->getAllMembers();
            $stats = $admin->getMemberStats();

            $this->view('admin/member_list', [
                'members' => $allMembers,
                'stats' => $stats
            ]);
        } catch (Exception $e) {
            $_SESSION['error'] = "Error fetching members: " . $e->getMessage();
            $this->view('admin/member_list', [
                'members' => [], 
                'stats' => [
                    'total' => 0,
                    'pending' => 0,
                    'active' => 0,
                    'rejected' => 0,
                    'resigned' => 0
                ]
            ]);
        }
    }

    public function uploadReport()
    {
        try {
            if (!isset($_FILES['report_file']) || $_FILES['report_file']['error'] !== UPLOAD_ERR_OK) {
                throw new \Exception('Sila pilih fail untuk dimuat naik');
            }

            $file = $_FILES['report_file'];
            $year = $_POST['year'] ?? '';
            $title = $_POST['title'] ?? '';

            if (empty($year) || empty($title)) {
                throw new \Exception('Sila isi semua maklumat yang diperlukan');
            }

            $allowedTypes = ['application/pdf'];
            $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($fileInfo, $file['tmp_name']);
            finfo_close($fileInfo);

            if (!in_array($mimeType, $allowedTypes)) {
                throw new \Exception('Hanya fail PDF diterima');
            }

            $maxSize = 100 * 1024 * 1024; 
            if ($file['size'] > $maxSize) {
                throw new \Exception('Saiz fail tidak boleh melebihi 10MB');
            }

            $uploadDir = dirname(__DIR__, 2) . '/public/uploads/annual-reports/';
            if (!file_exists($uploadDir)) {
                if (!mkdir($uploadDir, 0777, true)) {
                    throw new \Exception('Gagal membuat direktori');
                }
            }

            $fileName = 'annual_report_' . $year . '_' . uniqid() . '.pdf';
            $filePath = $uploadDir . $fileName;

            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new \Exception('Gagal memuat naik fail');
            }

            $data = [
                'year' => $year,
                'title' => $year,
                'filename' => $fileName,
                'file_path' => '/uploads/annual-reports/' . $fileName,
                'uploaded_by' => $_SESSION['admin_id']
            ];

            $this->annualReport->create($data);

            $_SESSION['success'] = 'Laporan tahunan berjaya dimuat naik';
            header('Location: /admin');
            exit;
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Ralat semasa memuat naik fail: ' . $e->getMessage();
            header('Location: /admin');
            exit;
        }
    }

    public function downloadReport($id)
    {
        try {
            $report = $this->annualReport->getById($id);
            if (!$report) {
                throw new \Exception('Laporan tidak dijumpai');
            }

            $filepath = dirname(__DIR__, 2) . '/public/uploads/annual-reports/' . $report['filename'];
            if (!file_exists($filepath)) {
                throw new \Exception('Fail tidak dijumpai');
            }

            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $report['filename'] . '"');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;

        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: /admin/annual-reports');
            exit;
        }
    }

    public function deleteReport($id)
    {
        try {
            $report = $this->annualReport->getById($id);
            if (!$report) {
                throw new \Exception('Laporan tidak dijumpai');
            }

            if (!$this->annualReport->delete($id)) {
                throw new \Exception('Gagal memadam rekod dari pangkalan data');
            }

            $filepath = dirname(__DIR__, 2) . '/public/uploads/annual-reports/' . $report['file_name'];
            
            if (file_exists($filepath)) {
                chmod($filepath, 0777);
                clearstatcache(true, $filepath);
                
                if (!unlink($filepath)) {
                    $error = error_get_last();
                    throw new \Exception('Gagal memadam fail: ' . ($error['message'] ?? 'Unknown error'));
                }
            }

            $_SESSION['success'] = 'Laporan tahunan berjaya dipadam';
            header('Location: /admin');
            exit;

        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: /admin');
            exit;
        }
    }

    public function updateInterestRates()
    {
        try {
            if (!isset($_POST['savings_rate']) || !isset($_POST['loan_rate'])) {
                throw new \Exception('Sila isi semua maklumat yang diperlukan');
            }

            $data = [
                'savings_rate' => (float)$_POST['savings_rate'],
                'loan_rate' => (float)$_POST['loan_rate']
            ];

            if ($this->admin->updateInterestRates($data)) {
                $_SESSION['success'] = 'Kadar faedah berjaya dikemaskini';
            } else {
                throw new \Exception('Gagal mengemaskini kadar faedah');
            }

        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: /admin');
        exit;
    }

    public function showResignations()
    {
        try {
            $pendingResignations = $this->admin->getPendingResignations();
            $this->view('admin/resignations', [
                'resignations' => $pendingResignations
            ]);
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: /admin/dashboard');
            exit();
        }
    }

    public function approveResignation()
    {
        try {
            if (!isset($_POST['member_id'])) {
                throw new \Exception('ID Ahli tidak sah');
            }

            $memberId = $_POST['member_id'];
            
            // Add debug logging
            error_log('Approving resignation for member ID: ' . $memberId);
            
            if ($this->admin->approveResignation($memberId)) {
                $_SESSION['success'] = 'Permohonan berhenti telah diluluskan';
                header('Location: /admin/resignations');
                exit();
            } else {
                throw new \Exception('Gagal meluluskan permohonan');
            }

        } catch (\Exception $e) {
            error_log('Error in approveResignation: ' . $e->getMessage());
            $_SESSION['error'] = $e->getMessage();
            header('Location: /admin/resignations');
            exit();
        }
    }

    public function showAddAdmin()
    {
        $this->view('admin/add_admin');
    }

    public function storeAdmin()
    {
        try {
            $username = $_POST['username'] ?? '';
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($username) || empty($email) || empty($password)) {
                throw new \Exception('Sila isi semua maklumat yang diperlukan');
            }

            if ($password !== $confirmPassword) {
                throw new \Exception('Kata laluan tidak sepadan');
            }

            if (strlen($password) < 8) {
                throw new \Exception('Kata laluan mestilah sekurang-kurangnya 8 aksara');
            }

            $data = [
                'username' => $username,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT)
            ];

            if ($this->admin->createAdmin($data)) {
                $_SESSION['success'] = 'Admin baru berjaya ditambah';
                header('Location: /admin');
                exit;
            }

            throw new \Exception('Gagal menambah admin');

        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: /admin/add-admin');
            exit;
        }
    }

    public function deleteAdmin($id)
    {
        try {
            if ($id == $_SESSION['admin_id']) {
                throw new \Exception('Tidak boleh memadam akaun sendiri');
            }

            if ($this->admin->deleteAdmin($id)) {
                $_SESSION['success'] = 'Admin berjaya dipadam';
            } else {
                throw new \Exception('Gagal memadam admin');
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: /admin');
        exit;
    }

    public function showEditProfile()
    {
        try {
            if (!isset($_SESSION['admin_id'])) {
                throw new \Exception('Sila log masuk sebagai admin');
            }

            $admin = $this->admin->getAdminById($_SESSION['admin_id']);
            if (!$admin) {
                throw new \Exception('Profil admin tidak dijumpai');
            }

            $this->view('admin/edit_profile', ['admin' => $admin]);
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: /admin');
            exit;
        }
    }

    public function updateProfile()
    {
        try {
            if (!isset($_SESSION['admin_id'])) {
                throw new \Exception('Sila log masuk sebagai admin');
            }

            $username = $_POST['username'] ?? '';
            $email = $_POST['email'] ?? '';
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($username) || empty($email)) {
                throw new \Exception('Sila isi semua maklumat yang diperlukan');
            }

            $data = [
                'id' => $_SESSION['admin_id'],
                'username' => $username,
                'email' => $email
            ];

            // If changing password
            if (!empty($currentPassword)) {
                if (empty($newPassword) || empty($confirmPassword)) {
                    throw new \Exception('Sila isi semua medan kata laluan');
                }

                if ($newPassword !== $confirmPassword) {
                    throw new \Exception('Kata laluan baru tidak sepadan');
                }

                if (strlen($newPassword) < 8) {
                    throw new \Exception('Kata laluan mestilah sekurang-kurangnya 8 aksara');
                }

                if (!$this->admin->verifyPassword($_SESSION['admin_id'], $currentPassword)) {
                    throw new \Exception('Kata laluan semasa tidak sah');
                }

                $data['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
            }

            if ($this->admin->updateAdmin($data)) {
                $_SESSION['success'] = 'Profil berjaya dikemaskini';
            } else {
                throw new \Exception('Gagal mengemaskini profil');
            }

        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: /admin/edit-profile');
        exit;
    }

    public function showEditAdmin($id)
    {
        try {
            if (!isset($_SESSION['admin_id'])) {
                throw new \Exception('Sila log masuk sebagai admin');
            }

            $admin = $this->admin->getAdminById($id);
            if (!$admin) {
                throw new \Exception('Admin tidak dijumpai');
            }

            $this->view('admin/edit_admin', ['admin' => $admin]);
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: /admin');
            exit;
        }
    }

    public function updateAdminById($id)
    {
        try {
            if (!isset($_SESSION['admin_id'])) {
                throw new \Exception('Sila log masuk sebagai admin');
            }

            $username = $_POST['username'] ?? '';
            $email = $_POST['email'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (empty($username) || empty($email)) {
                throw new \Exception('Sila isi semua maklumat yang diperlukan');
            }

            $data = [
                'id' => $id,
                'username' => $username,
                'email' => $email
            ];

            // If setting new password
            if (!empty($newPassword)) {
                if (empty($confirmPassword)) {
                    throw new \Exception('Sila sahkan kata laluan baru');
                }

                if ($newPassword !== $confirmPassword) {
                    throw new \Exception('Kata laluan baru tidak sepadan');
                }

                if (strlen($newPassword) < 8) {
                    throw new \Exception('Kata laluan mestilah sekurang-kurangnya 8 aksara');
                }

                $data['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
            }

            if ($this->admin->updateAdmin($data)) {
                $_SESSION['success'] = 'Admin berjaya dikemaskini';
                header('Location: /admin');
                exit;
            }

            throw new \Exception('Gagal mengemaskini admin');

        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: /admin/edit-admin/' . $id);
            exit;
        }
    }

    public function showEditDirector($id)
    {
        try {
            if (!isset($_SESSION['admin_id'])) {
                throw new \Exception('Sila log masuk sebagai admin');
            }

            $director = $this->admin->getDirectorById($id);
            if (!$director) {
                throw new \Exception('Pengarah tidak dijumpai');
            }

            $this->view('admin/edit_director', ['director' => $director]);
        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: /admin');
            exit;
        }
    }

    public function updateDirector($id)
    {
        try {
            if (!isset($_SESSION['admin_id'])) {
                throw new \Exception('Sila log masuk sebagai admin');
            }

            $name = $_POST['name'] ?? '';
            $position = $_POST['position'] ?? '';
            $email = $_POST['email'] ?? '';
            $phone_number = $_POST['phone_number'] ?? '';

            if (empty($name) || empty($position)) {
                throw new \Exception('Sila isi semua maklumat yang diperlukan');
            }

            $data = [
                'id' => $id,
                'name' => $name,
                'position' => $position,
                'email' => $email,
                'phone_number' => $phone_number
            ];

            if ($this->admin->updateDirector($data)) {
                $_SESSION['success'] = 'Maklumat pengarah berjaya dikemaskini';
                header('Location: /admin');
                exit;
            }

            throw new \Exception('Gagal mengemaskini maklumat pengarah');

        } catch (\Exception $e) {
            $_SESSION['error'] = $e->getMessage();
            header('Location: /admin/edit-director/' . $id);
            exit;
        }
    }
}