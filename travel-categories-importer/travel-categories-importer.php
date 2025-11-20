<?php
/**
 * Plugin Name: Travel Categories Importer
 * Description: Import kategori travel dari file Excel secara otomatis
 * Version: 8.0.0
 * Author: Assistant
 */

if (!defined('ABSPATH')) exit;

define('TCI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TCI_PLUGIN_PATH', plugin_dir_path(__FILE__));

class TravelCategoriesImporter {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Travel Importer',
            'Travel Importer',
            'manage_options',
            'travel-importer',
            array($this, 'admin_page'),
            'dashicons-location-alt',
            30
        );
    }
    
    public function enqueue_scripts($hook) {
        if ($hook !== 'toplevel_page_travel-importer') return;
        wp_enqueue_style('tci-styles', TCI_PLUGIN_URL . 'assets/style.css', array(), '8.0.0');
    }
    
    public function admin_page() {
        ?>
        <div class="wrap tci-wrapper">
            <h1>🚀 Travel Categories Importer</h1>
            
            <div class="notice notice-success">
                <p>✅ <strong>PhpSpreadsheet Ready!</strong> Output akan berupa Excel (.xlsx)</p>
            </div>
            
            <div class="tci-upload-section">
                <h2>Upload File CSV</h2>
                <p>Upload file CSV berisi data rute travel untuk membuat kategori otomatis</p>
                
                <form method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('tci_upload_nonce', 'tci_nonce'); ?>
                    <input type="file" name="csv_file" accept=".csv" required style="margin: 10px 0; padding: 10px; border: 2px dashed #ccc; border-radius: 4px; width: 100%; max-width: 400px;">
                    <button type="submit" name="process_file" class="button button-primary" style="font-size: 16px; padding: 12px 24px;">
                        🚀 Process CSV & Generate Excel
                    </button>
                </form>
                
                <div class="file-requirements">
                    <h3>Format CSV:</h3>
                    <table style="width: 100%; border-collapse: collapse; margin: 10px 0;">
                        <thead>
                            <tr style="background: #f0f0f0;">
                                <th style="border: 1px solid #ccc; padding: 8px;">START</th>
                                <th style="border: 1px solid #ccc; padding: 8px;">FINISH</th>
                                <th style="border: 1px solid #ccc; padding: 8px;">START</th>
                                <th style="border: 1px solid #ccc; padding: 8px;">FINISH</th>
                                <th style="border: 1px solid #ccc; padding: 8px;">PRICE</th>
                                <th style="border: 1px solid #ccc; padding: 8px;">ID J.TRANS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="border: 1px solid #ccc; padding: 8px;">Bandung;Banjar;BANDUNG;BANJAR;250.000;</td>
                                <td style="border: 1px solid #ccc; padding: 8px;">Banjar</td>
                                <td style="border: 1px solid #ccc; padding: 8px;">BANDUNG</td>
                                <td style="border: 1px solid #ccc; padding: 8px;">BANJAR</td>
                                <td style="border: 1px solid #ccc; padding: 8px;">250.000</td>
                                <td style="border: 1px solid #ccc; padding: 8px;"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <?php
            if (isset($_POST['process_file']) && isset($_FILES['csv_file'])) {
                $this->handle_file_upload();
            }
            ?>
        </div>
        <?php
    }
    
    private function handle_file_upload() {
        if (!wp_verify_nonce($_POST['tci_nonce'], 'tci_upload_nonce') || !current_user_can('manage_options')) {
            echo '<div class="error"><p>Security check failed.</p></div>'; return;
        }
        
        $file = $_FILES['csv_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo '<div class="error"><p>File upload error.</p></div>'; return;
        }
        
        if (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
            echo '<div class="error"><p>Please upload CSV file.</p></div>'; return;
        }
        
        $result = $this->process_csv_file($file['tmp_name']);
        
        if ($result['success']) {
            $this->show_success_results($result);
        } else {
            echo '<div class="error"><p>' . $result['message'] . '</p></div>';
        }
    }
    
    private function process_csv_file($file_path) {
        try {
            // Load Composer autoload
            require_once TCI_PLUGIN_PATH . 'vendor/autoload.php';
            
            $all_data = $this->read_csv_file($file_path);
            if (empty($all_data)) {
                return ['success' => false, 'message' => 'No valid data found in CSV.'];
            }
            
            $unique_cities = $this->get_unique_start_cities($all_data);
            $category_map = $this->create_categories_for_cities($unique_cities);
            $output_file = $this->generate_excel_output($all_data, $category_map);
            
            return [
                'success' => true,
                'total_routes' => count($all_data),
                'unique_cities' => count($unique_cities),
                'categories_created' => $this->count_created_categories($category_map),
                'output_file' => $output_file,
                'category_map' => $category_map
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    private function read_csv_file($file_path) {
        $all_data = [];
        if (($handle = fopen($file_path, "r")) === FALSE) {
            throw new Exception('Cannot open CSV file.');
        }
        
        fgetcsv($handle); // Skip header
        
        while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (empty($data[0])) continue;
            
            $parts = explode(';', trim($data[0]));
            if (count($parts) < 5) continue;
            
            $all_data[] = [
                'start_city' => trim($parts[0]),
                'finish_city' => trim($parts[1]),
                'start_upper' => trim($parts[2]),
                'finish_upper' => trim($parts[3]),
                'price' => trim($parts[4]),
                'route_id' => isset($data[5]) ? trim($data[5]) : ''
            ];
        }
        
        fclose($handle);
        return $all_data;
    }
    
    private function get_unique_start_cities($all_data) {
        $cities = [];
        foreach ($all_data as $data) {
            $city = $data['start_city'];
            if (!empty($city) && !in_array($city, $cities)) {
                $cities[] = $city;
            }
        }
        return $cities;
    }
    
    private function create_categories_for_cities($cities) {
        $category_map = [];
        foreach ($cities as $city) {
            $existing_term = term_exists($city, 'category');
            if ($existing_term) {
                $category_map[$city] = [
                    'city_name' => $city, 
                    'category_id' => $existing_term['term_id'], 
                    'status' => 'EXISTING'
                ];
            } else {
                $new_term = wp_insert_term($city, 'category', [
                    'slug' => sanitize_title($city),
                    'description' => 'Kota keberangkatan travel: ' . $city
                ]);
                
                if (!is_wp_error($new_term)) {
                    $category_map[$city] = [
                        'city_name' => $city, 
                        'category_id' => $new_term['term_id'], 
                        'status' => 'CREATED'
                    ];
                } else {
                    $category_map[$city] = [
                        'city_name' => $city, 
                        'category_id' => 'ERROR', 
                        'status' => 'FAILED: ' . $new_term->get_error_message()
                    ];
                }
            }
        }
        return $category_map;
    }
    
    private function generate_excel_output($all_data, $category_map) {
        $upload_dir = wp_upload_dir();
        $output_path = $upload_dir['path'] . '/travel_routes_' . date('Y-m-d_His') . '.xlsx';
        
        // Create spreadsheet
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Travel Routes');
        
        // Set headers
        $headers = ['NO', 'START CITY', 'FINISH CITY', 'START CITY (UPPER)', 'FINISH CITY (UPPER)', 'PRICE', 'ID J.TRANS', 'CATEGORY ID'];
        $sheet->fromArray($headers, null, 'A1');
        
        // Header style - BIRU TUA
        $headerStyle = [
            'font' => [
                'bold' => true, 
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => ['rgb' => '2E75B6']
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => '1F4E79']
                ]
            ]
        ];
        $sheet->getStyle('A1:H1')->applyFromArray($headerStyle);
        
        // Data rows
        $row = 2;
        foreach ($all_data as $index => $data) {
            $start_city = $data['start_city'];
            $category_id = isset($category_map[$start_city]) ? $category_map[$start_city]['category_id'] : 'NOT_FOUND';
            
            $sheet->fromArray([
                $index + 1,                    // NO
                $data['start_city'],           // START CITY
                $data['finish_city'],          // FINISH CITY
                $data['start_upper'],          // START CITY (UPPER)
                $data['finish_upper'],         // FINISH CITY (UPPER)
                $data['price'],                // PRICE
                $data['route_id'],             // ID J.TRANS
                $category_id                   // CATEGORY ID
            ], null, "A{$row}");
            
            $row++;
        }
        
        // Style data rows
        $last_row = count($all_data) + 1;
        $dataStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'DDDDDD']
                ]
            ],
            'alignment' => [
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
            ]
        ];
        $sheet->getStyle("A2:H{$last_row}")->applyFromArray($dataStyle);
        
        // Zebra stripe - BIRU MUDA
        for ($i = 2; $i <= $last_row; $i++) {
            if ($i % 2 == 0) {
                $sheet->getStyle("A{$i}:H{$i}")
                    ->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB('F0F8FF');
            }
        }
        
        // Style untuk CATEGORY ID - HIJAU & BOLD
        $sheet->getStyle("H2:H{$last_row}")
            ->getFont()
            ->setBold(true)
            ->getColor()
            ->setRGB('2E8B57');
        
        // Auto size columns
        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        
        // Set row height untuk header
        $sheet->getRowDimension(1)->setRowHeight(25);
        
        // Freeze header row
        $sheet->freezePane('A2');
        
        // Save file
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($output_path);
        
        return $upload_dir['url'] . '/' . basename($output_path);
    }
    
    private function count_created_categories($category_map) {
        $count = 0;
        foreach ($category_map as $category) {
            if ($category['status'] === 'CREATED') {
                $count++;
            }
        }
        return $count;
    }
    
    private function show_success_results($result) {
        ?>
        <div class="tci-results-section" style="background: #fff; padding: 30px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            <h2 style="color: #2E8B57;">✅ Processing Complete!</h2>
            
            <div class="results-summary" style="background: #e7f3ff; padding: 15px; border-radius: 4px; margin: 15px 0;">
                <h3>Summary:</h3>
                <ul>
                    <li><strong>Total Rute Diproses:</strong> <?php echo $result['total_routes']; ?></li>
                    <li><strong>Kota Unik Ditemukan:</strong> <?php echo $result['unique_cities']; ?></li>
                    <li><strong>Kategori Baru Dibuat:</strong> <?php echo $result['categories_created']; ?></li>
                </ul>
            </div>
            
            <div class="download-section">
                <h3>Download Hasil:</h3>
                <a href="<?php echo $result['output_file']; ?>" class="button button-primary" download style="display: inline-block; padding: 12px 24px; background: #2E75B6; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; font-size: 16px;">
                    📥 Download Excel File (.xlsx)
                </a>
                <p><small>File Excel berisi semua rute dengan Category ID untuk setiap kota START</small></p>
            </div>
            
            <?php if (!empty($result['category_map'])): ?>
            <div class="categories-list" style="margin-top: 20px;">
                <h3>📋 Daftar Kategori di WordPress:</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Kota START</th>
                            <th>Category ID</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result['category_map'] as $city_data): ?>
                        <tr>
                            <td><strong><?php echo esc_html($city_data['city_name']); ?></strong></td>
                            <td><code style="background: #2E8B57; color: white; padding: 2px 6px; border-radius: 3px;"><?php echo esc_html($city_data['category_id']); ?></code></td>
                            <td>
                                <span style="color: <?php echo $city_data['status'] === 'CREATED' ? '#00a32a' : '#d63638'; ?>; font-weight: bold;">
                                    <?php echo esc_html($city_data['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

new TravelCategoriesImporter();