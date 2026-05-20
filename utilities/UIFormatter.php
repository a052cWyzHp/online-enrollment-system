<?php
class UIFormatter {
    
    public static function formatStatusLabel(string $status): string {
        return ucwords(str_replace('_', ' ', $status));
    } // this is for turning status labels like "document_pending" into "Document Pending"

    public static function safeDate(?string $date): string {
        return !empty($date) ? date('M d, Y', strtotime($date)) : 'Not submitted';
    } // date formatter like 2026-05-10 to May 10, 2026

    public static function safeDateTime(?string $date): string {
        return !empty($date) ? date('M d, Y h:i A', strtotime($date)) : 'Not submitted';
    } // date and time formatter, basically same as before but adds something like "5:01 PM"

    public static function initialsFromName(string $name): string {
        $parts = preg_split('/\s+/', trim($name));

        if(count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
        }

        return strtoupper(substr($name, 0, 1));
    } // turns name into initials like "John Paul" to "J P"

    public static function statusBadgeClass(string $status): string {
        return match($status) {
            'fully_enrolled', 'enrolled' => 'bg-success-subtle text-success',
            'payment_pending', 'payment_submitted', 'under_review', 'documents_submitted' => 'bg-warning-subtle text-warning',
            'resubmission_required' => 'bg-info-subtle text-info',
            'rejected' => 'bg-danger-subtle text-danger',
            default => 'bg-secondary-subtle text-secondary'
        };
    } // this is for bootstrap colors depending on the enrollment status
}
?>