# Staff Attendance Management System

## Overview
The Staff Attendance Management System is a comprehensive solution for gym staff to monitor and manage member attendance. It provides real-time tracking, search functionality, manual check-in capabilities, and **QR code scanning integration** that automatically syncs with the admin QR scanning system.

## Features

### 1. Dashboard Overview
- **Real-time Statistics**: View total attendance, active sessions, and completed sessions for the current day
- **Date Selection**: Choose any date to view historical attendance data
- **Quick Actions**: Access manual check-in, print functionality, and QR scanner
- **Live Updates**: Real-time synchronization with admin QR scanning system

### 2. Attendance Tracking
- **Member Information**: Display member photos, names, contact numbers, and membership types
- **Time Tracking**: Monitor check-in and check-out times with duration calculations
- **Status Indicators**: Visual badges showing active vs. completed sessions
- **Real-time Updates**: Auto-refresh every 30 seconds + live QR scan updates

### 3. Search and Filter
- **Member Search**: Search by name or member code
- **Status Filtering**: Filter by active, completed, or all sessions
- **Real-time Results**: Instant search results with highlighting
- **Results Counter**: Shows number of filtered results

### 4. Manual Check-in
- **Member Code Input**: Enter member codes for manual check-ins
- **Validation**: Ensures member exists and is active
- **Duplicate Prevention**: Prevents multiple check-ins on the same day
- **Success Feedback**: Clear confirmation messages

### 5. **QR Code Scanner Integration** 🆕
- **Built-in QR Scanner**: Staff can scan QR codes directly from the attendance page
- **Real-time Sync**: Automatically updates when admin scans QR codes
- **Live Status**: Shows connection status to QR scanning system
- **Instant Updates**: New attendance records appear immediately after scanning
- **Camera Access**: Uses device camera for QR code scanning

### 6. Session Management
- **Check-out Functionality**: Staff can check out active members
- **Duration Calculation**: Automatic calculation of session duration
- **Status Updates**: Real-time status changes from active to completed
- **QR Integration**: Check-outs can be triggered by QR scans

### 7. Responsive Design
- **Mobile Optimized**: Works seamlessly on all device sizes
- **Print Friendly**: Optimized layout for printing attendance reports
- **Modern UI**: Clean, professional interface with smooth animations

## **QR Scanner Integration Details** 🔗

### **How It Works**
1. **Admin QR Scanning**: When admin scans QR codes using the admin system
2. **Database Update**: Attendance is automatically recorded in the database
3. **Staff Page Sync**: Staff attendance page automatically detects new records
4. **Real-time Display**: New attendance appears instantly without page refresh

### **Staff QR Scanner Features**
- **Direct Scanning**: Staff can scan QR codes from their own page
- **Camera Integration**: Uses device camera for scanning
- **Instant Processing**: QR codes are processed immediately
- **Success Feedback**: Clear confirmation of successful scans
- **Error Handling**: Helpful error messages for failed scans

### **Real-time Updates**
- **Live Indicator**: Shows "LIVE" status when connected
- **Auto-polling**: Checks for new data every 10 seconds
- **Instant Sync**: New records appear immediately
- **Stats Update**: Attendance counts update in real-time

## File Structure

```
staff/
├── attendance.php          # Main attendance management page with QR scanner
├── components/
│   ├── header.php         # Page header with navigation
│   ├── sidebar.php        # Navigation sidebar
│   └── footer.php         # Page footer
assets/
├── css/
│   └── staff/
│       └── attendance.css  # Attendance page styling
└── js/
    └── staff/
        └── attendance.js   # Attendance page functionality + QR scanner
qr/
├── scan.php               # QR code processing (shared with admin)
├── check_database.php     # API for real-time updates
└── attendance_actions.php # Attendance management actions
```

## Usage Instructions

### For Staff Members

1. **Accessing the System**
   - Navigate to the staff dashboard
   - Click on "Attendance" in the sidebar
   - You'll see today's attendance overview with live updates

2. **Viewing Attendance**
   - Default view shows today's attendance
   - Use date picker to view other dates
   - Scroll through the table to see all members
   - **Live updates appear automatically**

3. **Searching Members**
   - Use the search box to find specific members
   - Search by name or member code
   - Results update in real-time

4. **Filtering Results**
   - Use the filter dropdown to show:
     - All sessions
     - Active sessions only
     - Completed sessions only

5. **Manual Check-in**
   - Click "Manual Check-in" button
   - Enter the member's code
   - Click "Check In" to confirm
   - System validates member status

6. **QR Code Scanning** 🆕
   - Click "QR Scanner" button
   - Allow camera access when prompted
   - Point camera at member's QR code
   - Attendance is automatically recorded
   - Success message confirms the action

7. **Checking Out Members**
   - Find the member in the active sessions
   - Click "Check Out" button
   - Confirm the action
   - Status updates to completed
   - **Can also be done via QR scan**

8. **Printing Reports**
   - Click "Print" button
   - Page optimizes for printing
   - Print current view or filtered results

### **QR Scanner Usage** 📱

1. **Start Scanning**
   - Click "QR Scanner" button
   - Click "Start Scanning" in the modal
   - Allow camera permissions

2. **Scan QR Code**
   - Point camera at member's QR code
   - Hold steady until code is detected
   - Scanner will automatically process the code

3. **View Results**
   - Success message appears
   - New attendance record is added to table
   - Stats are updated immediately
   - Modal closes automatically after 2 seconds

### For Administrators

1. **System Configuration**
   - Ensure database tables are properly set up
   - Verify member and attendance data integrity
   - Check staff permissions and access levels
   - **QR codes are automatically synced between admin and staff**

2. **Monitoring Usage**
   - Review attendance logs regularly
   - Monitor manual check-in activities
   - Track system performance and errors
   - **QR scan activities are visible in both systems**

## Technical Features

### Database Integration
- Connects to existing gym management database
- Uses prepared statements for security
- Handles attendance archive tables
- **Real-time synchronization between admin and staff systems**

### Security Features
- Session-based authentication
- Staff role verification
- Input validation and sanitization
- SQL injection prevention
- **Secure QR code processing**

### Performance Optimizations
- Efficient database queries
- Responsive table rendering
- Optimized CSS and JavaScript
- Browser caching support
- **Real-time updates with minimal server load**

### **QR Scanner Technology**
- **jsQR Library**: Advanced QR code detection
- **Camera Integration**: Direct device camera access
- **Real-time Processing**: Instant QR code recognition
- **Error Handling**: Graceful fallbacks for failed scans

## Browser Support

- **Chrome**: Full support (including camera access)
- **Firefox**: Full support (including camera access)
- **Safari**: Full support (with webkit prefixes, camera access)
- **Edge**: Full support (including camera access)
- **Mobile Browsers**: Responsive design + camera support

## Troubleshooting

### Common Issues

1. **Page Not Loading**
   - Check staff login session
   - Verify database connection
   - Check file permissions

2. **Search Not Working**
   - Ensure JavaScript is enabled
   - Check browser console for errors
   - Verify search input field exists

3. **Manual Check-in Fails**
   - Verify member code is correct
   - Check if member is already checked in
   - Ensure member status is active

4. **Auto-refresh Issues**
   - Check if page is visible
   - Verify JavaScript is running
   - Check for console errors

### **QR Scanner Issues** 🔧

5. **Camera Not Working**
   - Ensure camera permissions are granted
   - Check if another app is using the camera
   - Try refreshing the page and granting permissions again

6. **QR Code Not Detected**
   - Ensure good lighting conditions
   - Hold camera steady and close to QR code
   - Check if QR code is damaged or obscured

7. **Scan Success But No Update**
   - Check internet connection
   - Verify database connection
   - Check browser console for errors

### Error Messages

- **"Member not found"**: Invalid member code entered
- **"Member is already checked in"**: Duplicate check-in attempt
- **"Failed to check in member"**: Database or system error
- **"Failed to update attendance"**: Check-out process error
- **"Camera access denied"**: Camera permissions not granted
- **"QR code not detected"**: Camera unable to read QR code

## **Real-time Update System** ⚡

### **How Real-time Updates Work**
1. **Admin scans QR code** → Database updated
2. **Staff page polls database** every 10 seconds
3. **New records detected** → Table updated automatically
4. **Stats recalculated** → Display updated immediately
5. **Visual feedback** → Success messages and animations

### **Update Frequency**
- **QR Scan Updates**: Immediate (when scanning from staff page)
- **Admin Sync Updates**: Every 10 seconds (automatic polling)
- **Manual Refresh**: Every 30 seconds (fallback)
- **User Actions**: Instant (manual check-in/out)

## Future Enhancements

1. **Enhanced QR Integration**: 
   - Bulk QR code processing
   - QR code generation for staff
   - Offline QR scanning support

2. **Advanced Real-time Features**:
   - WebSocket connections for instant updates
   - Push notifications for new attendance
   - Real-time chat between admin and staff

3. **Mobile App**: Native mobile application for staff
4. **Analytics Dashboard**: Advanced reporting and insights
5. **Email Notifications**: Automated attendance confirmations
6. **API Integration**: Connect with external systems

## Support

For technical support or feature requests, contact the development team or refer to the main system documentation.

---

**Last Updated**: December 2024
**Version**: 2.0.0 (with QR Scanner Integration)
**System**: FIT_TRACK Gym Management
**New Features**: QR Scanner, Real-time Updates, Live Sync
