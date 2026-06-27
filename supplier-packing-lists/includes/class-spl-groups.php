<?php
class SPL_Groups {
    private $db;
    
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
    }
    
    public function get_all_groups() {
        $table = $this->db->prefix . 'spl_groups';
        $pl_table = $this->db->prefix . 'spl_group_pls';
        
        $query = "
            SELECT g.*, COUNT(gp.pl_id) as pl_count 
            FROM $table g 
            LEFT JOIN $pl_table gp ON g.id = gp.group_id 
            GROUP BY g.id 
            ORDER BY g.created_at DESC
        ";
        
        return $this->db->get_results($query);
    }
    
    public function create_group($name, $description, $color) {
        $table = $this->db->prefix . 'spl_groups';
        
        $result = $this->db->insert(
            $table,
            array(
                'name' => $name,
                'description' => $description,
                'color' => $color
            ),
            array('%s', '%s', '%s')
        );
        
        return $result ? $this->db->insert_id : false;
    }
    
    public function assign_pl_to_group($pl_id, $group_id) {
        $table = $this->db->prefix . 'spl_group_pls';
        
        // Remove existing assignment first
        $this->db->delete($table, array('pl_id' => $pl_id), array('%d'));
        
        return $this->db->insert(
            $table,
            array('group_id' => $group_id, 'pl_id' => $pl_id),
            array('%d', '%d')
        );
    }
    
    public function get_pl_group($pl_id) {
        $table_groups = $this->db->prefix . 'spl_groups';
        $table_group_pls = $this->db->prefix . 'spl_group_pls';
        
        $query = $this->db->prepare("
            SELECT g.* 
            FROM $table_groups g
            JOIN $table_group_pls gp ON g.id = gp.group_id
            WHERE gp.pl_id = %d
            LIMIT 1
        ", $pl_id);
        
        return $this->db->get_row($query);
    }
}
