# Announcement System Setup Guide

## Overview
The announcement system allows administrators to create, manage, and display announcements to different user types (members, staff, walk-in customers) with various priority levels and targeting options.

## Features
- ✅ Create, edit, delete announcements
- ✅ Priority levels (Low, Medium, High, Urgent)
- ✅ Target audience selection (All, Members, Staff, Walk-in)
- ✅ Pin/unpin announcements
- ✅ Active/inactive status
- ✅ Expiration dates
- ✅ View tracking
- ✅ Search and filtering
- ✅ Responsive design
- ✅ Real-time AJAX operations

## Database Setup

### 1. Run the SQL Script
Execute the `create_announcements_table.sql` file in your database:

```sql
-- Run this script to create the announcements tables
source create_announcements_table.sql;
```

### 2. Verify Tables Created
Check that the following tables were created:
- `announcements` - Main announcements table
- `announcement_views` - Track who has viewed announcements

## File Structure

```
admin/
├── announcement.php              # Main announcement management page
├── announcement_actions.php      # AJAX handler for CRUD operations
└── components/
    ├── header.php               # Include this for admin layout
    └── footer.php               # Include this for admin layout

assets/
├── css/admin/
│   └── announcement.css         # Announcement-specific styles
└── js/admin/
    └── announcement.js          # Announcement functionality
```

## Usage

### For Administrators

1. **Access the Announcement Page**
   - Navigate to Admin Panel → Announcements
   - Or use the sidebar menu item

2. **Create New Announcement**
   - Click "Add Announcement" button
   - Fill in the required fields:
     - Title (required)
     - Message (required)
     - Priority (Low/Medium/High/Urgent)
     - Target Audience (All/Members/Staff/Walk-in)
     - Expiration Date (optional)
     - Pin to top (optional)

3. **Manage Existing Announcements**
   - Use the dropdown menu on each announcement
   - Options: Edit, Pin/Unpin, Activate/Deactivate, Delete
   - Use filters to find specific announcements

4. **Filtering Options**
   - Status: Active/Inactive/All
   - Priority: Low/Medium/High/Urgent/All
   - Target Audience: All/Members/Staff/Walk-in
   - Search: Text search in title and message

### For Other User Types

To display announcements to members, staff, or walk-in users, you can use the `AnnouncementManager` class:

```javascript
// Get announcements for a specific user type
announcementManager.getAnnouncements({
    status: 'active',
    target_audience: 'members' // or 'staff', 'walk_in', 'all'
}).then(announcements => {
    // Display announcements in your UI
    console.log(announcements);
});

// Mark announcement as viewed
announcementManager.markAsViewed(announcementId, userId, userType);
```

## API Endpoints

### POST /admin/announcement_actions.php

**Create Announcement:**
```javascript
{
    action: 'create',
    title: 'Announcement Title',
    message: 'Announcement content...',
    priority: 'medium',
    target_audience: 'all',
    expires_at: '2024-12-31 23:59:59', // optional
    is_pinned: 1 // optional
}
```

**Update Announcement:**
```javascript
{
    action: 'update',
    id: 1,
    title: 'Updated Title',
    message: 'Updated content...',
    priority: 'high',
    target_audience: 'members',
    expires_at: '2024-12-31 23:59:59',
    is_pinned: 0
}
```

**Delete Announcement:**
```javascript
{
    action: 'delete',
    id: 1
}
```

**Toggle Status:**
```javascript
{
    action: 'toggle_status',
    id: 1
}
```

**Toggle Pin:**
```javascript
{
    action: 'toggle_pin',
    id: 1
}
```

**Mark as Viewed:**
```javascript
{
    action: 'mark_viewed',
    announcement_id: 1,
    user_id: 123,
    user_type: 'member' // or 'staff', 'admin', 'walk_in'
}
```

### GET /admin/announcement_actions.php

**Get All Announcements:**
```
?action=get_announcements&status=active&target_audience=all&limit=50&offset=0
```

**Get Single Announcement:**
```
?action=get_announcement&id=1
```

## Customization

### Styling
Modify `assets/css/admin/announcement.css` to customize:
- Colors and themes
- Layout and spacing
- Responsive breakpoints
- Animations and transitions

### Functionality
Modify `assets/js/admin/announcement.js` to customize:
- AJAX behavior
- Form validation
- UI interactions
- Filtering logic

### Database
The announcement system uses these main fields:
- `title` - Announcement title
- `message` - Announcement content
- `priority` - Low/Medium/High/Urgent
- `target_audience` - All/Members/Staff/Walk-in
- `status` - Active/Inactive
- `is_pinned` - Boolean for pinning
- `expires_at` - Optional expiration date
- `views_count` - Number of views
- `created_by` - Admin who created it
- `created_at` - Creation timestamp

## Security Features

- ✅ SQL injection prevention with prepared statements
- ✅ XSS prevention with htmlspecialchars()
- ✅ CSRF protection (implement as needed)
- ✅ Input validation and sanitization
- ✅ Admin authentication required
- ✅ Proper error handling

## Troubleshooting

### Common Issues

1. **Announcements not displaying**
   - Check if the database tables exist
   - Verify the SQL script was executed
   - Check for JavaScript errors in browser console

2. **AJAX requests failing**
   - Verify file paths are correct
   - Check PHP error logs
   - Ensure admin is logged in

3. **Styling issues**
   - Verify CSS file is loaded
   - Check for CSS conflicts
   - Ensure Bootstrap is included

4. **Database errors**
   - Check database connection
   - Verify table structure
   - Check for missing columns

### Debug Mode
Enable debug mode by adding this to the top of `announcement_actions.php`:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Integration Examples

### Display in Member Dashboard
```php
<?php
// In member/dashboard.php
$sql = "SELECT * FROM announcements 
        WHERE status = 'active' 
        AND (target_audience = 'all' OR target_audience = 'members')
        AND (expires_at IS NULL OR expires_at > NOW())
        ORDER BY is_pinned DESC, priority DESC, created_at DESC
        LIMIT 5";
$result = $conn->query($sql);

while ($announcement = $result->fetch_assoc()) {
    echo '<div class="alert alert-info">';
    echo '<h5>' . htmlspecialchars($announcement['title']) . '</h5>';
    echo '<p>' . nl2br(htmlspecialchars($announcement['message'])) . '</p>';
    echo '</div>';
}
?>
```

### Display in Staff Dashboard
```php
<?php
// In staff/dashboard.php
$sql = "SELECT * FROM announcements 
        WHERE status = 'active' 
        AND (target_audience = 'all' OR target_audience = 'staff')
        AND (expires_at IS NULL OR expires_at > NOW())
        ORDER BY is_pinned DESC, priority DESC, created_at DESC
        LIMIT 5";
$result = $conn->query($sql);

while ($announcement = $result->fetch_assoc()) {
    echo '<div class="alert alert-warning">';
    echo '<h5>' . htmlspecialchars($announcement['title']) . '</h5>';
    echo '<p>' . nl2br(htmlspecialchars($announcement['message'])) . '</p>';
    echo '</div>';
}
?>
```

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Review the browser console for JavaScript errors
3. Check PHP error logs
4. Verify database connectivity and table structure

## Version History

- **v1.0** - Initial release with full CRUD functionality
- **v1.1** - Added filtering and search capabilities
- **v1.2** - Added view tracking and expiration dates
- **v1.3** - Added responsive design and improved UI
