<?php
class TCI_Category_Manager {
    
    public function process_routes($routes) {
        $category_map = array();
        $processed_cities = array();
        $created_count = 0;
        $existing_count = 0;
        
        foreach ($routes as $route) {
            $start_city = $route['start_city'];
            
            if (empty($start_city) || in_array($start_city, $processed_cities)) {
                continue;
            }
            
            $category_data = $this->get_or_create_category($start_city);
            $category_map[$start_city] = $category_data;
            $processed_cities[] = $start_city;
            
            if ($category_data['status'] === 'CREATED') {
                $created_count++;
            } else {
                $existing_count++;
            }
        }
        
        return array(
            'category_map' => $category_map,
            'created_count' => $created_count,
            'existing_count' => $existing_count
        );
    }
    
    private function get_or_create_category($city_name) {
        $slug = sanitize_title($city_name);
        
        $existing_term = term_exists($city_name, 'category');
        
        if ($existing_term) {
            $term_id = $existing_term['term_id'];
            $status = "EXISTING";
        } else {
            $new_term = wp_insert_term($city_name, 'category', array(
                'slug' => $slug,
                'description' => 'Kota keberangkatan travel: ' . $city_name
            ));
            
            if (!is_wp_error($new_term)) {
                $term_id = $new_term['term_id'];
                $status = "CREATED";
            } else {
                $term_id = "ERROR";
                $status = "FAILED: " . $new_term->get_error_message();
            }
        }
        
        return array(
            'city_name' => $city_name,
            'category_id' => $term_id,
            'slug' => $slug,
            'status' => $status
        );
    }
}
?>