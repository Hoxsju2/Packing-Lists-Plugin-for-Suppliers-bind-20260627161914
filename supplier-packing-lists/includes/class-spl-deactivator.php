<?php
class SPL_Deactivator {
    public static function deactivate() {
        // We do not drop tables on deactivation to preserve user data.
        flush_rewrite_rules();
    }
}
