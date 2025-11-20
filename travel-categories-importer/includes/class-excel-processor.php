<?php
class TCI_Excel_Processor {
    
    public function process_file($file_path, $file_type) {
        try {
            // Load the file based on type
            if ($file_type === 'csv') {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file_path);
            } else {
                $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader(ucfirst($file_type));
                $spreadsheet = $reader->load($file_path);
            }
            
            $worksheet = $spreadsheet->getActiveSheet();
            $routes = $this->extract_routes_from_sheet($worksheet);
            
            // Process categories
            $category_manager = new TCI_Category_Manager();
            $results = $category_manager->process_routes($routes);
            
            // Generate output file
            $output_file = $this->generate_output_file($routes, $results['category_map']);
            
            return array(
                'success' => true,
                'routes_processed' => count($routes),
                'categories_created' => $results['created_count'],
                'categories_existing' => $results['existing_count'],
                'output_file' => $output_file,
                'category_map' => $results['category_map']
            );
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'Error processing file: ' . $e->getMessage()
            );
        }
    }
    
    private function extract_routes_from_sheet($worksheet) {
        $routes = array();
        $start_row = 2; // Assume row 1 is header
        
        foreach ($worksheet->getRowIterator($start_row) as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            
            $row_data = array();
            foreach ($cellIterator as $cell) {
                $row_data[] = $cell->getCalculatedValue();
            }
            
            // Skip empty rows
            if (empty(trim($row_data[0]))) {
                continue;
            }
            
            $routes[] = array(
                'start_city' => trim($row_data[0]),
                'finish_city' => trim($row_data[1]),
                'start_city_upper' => isset($row_data[2]) ? trim($row_data[2]) : '',
                'finish_city_upper' => isset($row_data[3]) ? trim($row_data[3]) : '',
                'price' => isset($row_data[4]) ? trim($row_data[4]) : '',
                'route_id' => isset($row_data[5]) ? trim($row_data[5]) : ''
            );
        }
        
        return $routes;
    }
    
    private function generate_output_file($routes, $category_map) {
        $upload_dir = wp_upload_dir();
        $output_filename = 'travel_routes_with_categories_' . date('Y-m-d_His') . '.xlsx';
        $output_path = $upload_dir['path'] . '/' . $output_filename;
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Routes with Categories');
        
        // Headers
        $headers = ['No', 'Start City', 'Finish City', 'Category ID', 'Price', 'Route ID', 'Status'];
        $sheet->fromArray($headers, null, 'A1');
        
        // Data
        $row = 2;
        foreach ($routes as $index => $route) {
            $start_city = $route['start_city'];
            $category_data = $category_map[$start_city];
            
            $sheet->fromArray([
                $index + 1,
                $start_city,
                $route['finish_city'],
                $category_data['category_id'],
                $route['price'],
                $route['route_id'],
                $category_data['status']
            ], null, "A$row");
            
            $row++;
        }
        
        // Save file
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($output_path);
        
        return $upload_dir['url'] . '/' . $output_filename;
    }
}
?>