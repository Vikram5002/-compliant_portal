<?php
// Navigation Helper - Generates consistent navigation across all pages
// Include this file after db_connect.php to use the navigation functions

function getCurrentPageName() {
    $page = basename($_SERVER['PHP_SELF']);
    return str_replace('.php', '', $page);
}

function isActivePage($pageName) {
    return getCurrentPageName() === $pageName ? 'active' : '';
}

function generateNavigation($userRole, $userId = null) {
    global $conn, $unread_count;
    
    $currentPage = getCurrentPageName();
    $navItems = [];
    
    // Base items for all authenticated users
    $navItems[] = [
        'icon' => 'fas fa-tachometer-alt',
        'label' => 'Dashboard',
        'href' => 'index.php',
        'page' => 'index'
    ];
    
    // Role-based navigation
    if (strpos($userRole, 'sub_') === 0) {
        // Sub-Staff navigation
        $navItems[] = [
            'icon' => 'fas fa-tasks',
            'label' => 'My Tasks',
            'href' => 'sub_staff_dashboard.php',
            'page' => 'sub_staff_dashboard'
        ];
    } elseif (in_array($userRole, ['staff', 'maintenance', 'warden', 'security', 'house_keeping'])) {
        // Parent Staff navigation
        $navItems[] = [
            'icon' => 'fas fa-tasks',
            'label' => 'My Tickets',
            'href' => 'staff_tickets_with_sub.php',
            'page' => 'staff_tickets_with_sub'
        ];
    } elseif (in_array($userRole, ['rector', 'network/it_team'])) {
        // Rector/IT Team navigation
        $navItems[] = [
            'icon' => 'fas fa-tasks',
            'label' => 'Assigned Tickets',
            'href' => 'staff_tickets.php',
            'page' => 'staff_tickets'
        ];
    } elseif ($userRole === 'student') {
        // Student navigation
        $navItems[] = [
            'icon' => 'fas fa-ticket-alt',
            'label' => 'My Tickets',
            'href' => 'view_tickets.php',
            'page' => 'view_tickets'
        ];
    }
    
    // Admin/Super Visor specific items
    if (in_array($userRole, ['admin', 'super_visor'])) {
        $navItems[] = [
            'icon' => 'fas fa-user-shield',
            'label' => 'Admin Dashboard',
            'href' => 'admin_dashboard.php',
            'page' => 'admin_dashboard'
        ];
        $navItems[] = [
            'icon' => 'fas fa-user-plus',
            'label' => 'Add Users',
            'href' => 'bulk_import.php',
            'page' => 'bulk_import'
        ];
    }
    
    // Notifications (for all except student on certain pages)
    if ($userRole !== 'student' || $currentPage !== 'view_tickets') {
        $notifBadge = isset($unread_count) && $unread_count > 0 ? '<span class="notif-badge">' . $unread_count . '</span>' : '';
        $navItems[] = [
            'icon' => 'fas fa-bell',
            'label' => 'Notifications' . $notifBadge,
            'href' => 'notifications.php',
            'page' => 'notifications',
            'raw_label' => true
        ];
    }
    
    // Logout
    $navItems[] = [
        'icon' => 'fas fa-sign-out-alt',
        'label' => 'Logout',
        'href' => 'logout.php',
        'page' => 'logout'
    ];
    
    // Generate HTML
    $html = '<nav class="nav-menu">';
    foreach ($navItems as $item) {
        $activeClass = $currentPage === $item['page'] ? 'active' : '';
        $label = isset($item['raw_label']) && $item['raw_label'] ? $item['label'] : htmlspecialchars($item['label']);
        $html .= '<a href="' . htmlspecialchars($item['href']) . '" class="nav-item ' . $activeClass . '">';
        $html .= '<i class="' . htmlspecialchars($item['icon']) . '"></i>';
        $html .= '<span>' . $label . '</span>';
        $html .= '</a>';
    }
    $html .= '</nav>';
    
    return $html;
}
?>
