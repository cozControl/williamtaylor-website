# Announcement Management

Announcements are independent records with typed plain-text payloads, optional typed CTA, bounded variant, dismissibility and priority. Each has independent draft, candidate, designation, schedule and archive state. Active/scheduled interval overlap is blocked deterministically; adjacent intervals are allowed. Archived, cancelled and unpublished records do not conflict.
