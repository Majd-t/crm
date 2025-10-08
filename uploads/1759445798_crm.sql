

  CREATE TABLE `activity_log` (
    `id` int(11) NOT NULL,
    `staff_id` int(11) NOT NULL,
    `action_type` varchar(100) NOT NULL,
    `description` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp()
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


  --
  -- Tablo için tablo yapısı `admin`
  --

  CREATE TABLE `admin` (
    `id` int(11) NOT NULL,
    `username` varchar(50) NOT NULL,
    `email` varchar(100) NOT NULL,
    `password` varchar(255) NOT NULL,
    `created_at` datetime DEFAULT current_timestamp()
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


  -- Tablo için tablo yapısı `client_files`
  --

  CREATE TABLE `client_files` (
    `id` int(11) NOT NULL,
    `client_id` int(11) NOT NULL,
    `file_name` varchar(255) NOT NULL,
    `description` text DEFAULT NULL,
    `uploaded_by` varchar(50) NOT NULL,
    `created_at` datetime DEFAULT current_timestamp()
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


  -- Tablo için tablo yapısı `client_notes`
  --

  CREATE TABLE `client_notes` (
    `id` int(11) NOT NULL,
    `client_id` int(11) NOT NULL,
    `added_by` varchar(50) NOT NULL,
    `note` text NOT NULL,
    `created_at` datetime DEFAULT current_timestamp()
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


  --
  -- Tablo için tablo yapısı `customers`
  --

  CREATE TABLE `customers` (
    `id` int(11) NOT NULL,
    `staff_id` int(11) DEFAULT NULL,
    `name` varchar(150) NOT NULL,
    `phone` varchar(20) DEFAULT NULL,
    `email` varchar(150) DEFAULT NULL,
    `address` text DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `status` varchar(20) DEFAULT 'active',
    `password` varchar(255) NOT NULL,
    `assigned_staff_id` int(11) DEFAULT NULL,
    `status_id` int(11) DEFAULT NULL,
    `city` varchar(100) DEFAULT NULL,
    `no_response_status_id` int(11) DEFAULT NULL
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


  -- Tablo için tablo yapısı `customer_statuses`
  --

  CREATE TABLE `customer_statuses` (
    `id` int(11) NOT NULL,
    `name` varchar(50) NOT NULL,
    `description` text DEFAULT NULL,
    `color` varchar(7) NOT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    `sort_order` int(11) NOT NULL DEFAULT 0
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


  --
  -- Tablo için tablo yapısı `customer_status_log`
  --

  CREATE TABLE `customer_status_log` (
    `id` int(11) NOT NULL,
    `customer_id` int(11) NOT NULL,
    `staff_id` int(11) NOT NULL,
    `changed_by` varchar(255) NOT NULL,
    `type` enum('created','assigned','status','note','file') NOT NULL,
    `description` text DEFAULT NULL,
    `added_by` varchar(255) DEFAULT NULL,
    `created_at` datetime DEFAULT current_timestamp()
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


  --
  -- Tablo için tablo yapısı `messages`
  --

  CREATE TABLE `messages` (
    `id` int(11) NOT NULL,
    `sender_type` enum('admin','staff') NOT NULL,
    `sender_id` int(11) NOT NULL,
    `receiver_type` enum('admin','staff') NOT NULL,
    `receiver_id` int(11) NOT NULL,
    `message` text NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `status` tinyint(4) DEFAULT 0
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


  --
  -- Tablo için tablo yapısı `notifications`
  --

  CREATE TABLE `notifications` (
    `id` int(11) NOT NULL,
    `user_type` enum('admin','staff') NOT NULL,
    `user_id` int(11) NOT NULL,
    `type` enum('new_message','new_client','client_assigned','client_activity') NOT NULL,
    `message` text NOT NULL,
    `related_id` int(11) DEFAULT NULL,
    `is_read` tinyint(4) DEFAULT 0,
    `created_at` datetime DEFAULT current_timestamp()
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

  -- Tablo için tablo yapısı `no_response_statuses`
  --

  CREATE TABLE `no_response_statuses` (
    `id` int(11) NOT NULL,
    `name` varchar(50) NOT NULL,
    `description` text DEFAULT NULL,
    `color` varchar(7) NOT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


  --
  -- Tablo için tablo yapısı `staff`
  --

  CREATE TABLE `staff` (
    `id` int(11) NOT NULL,
    `username` varchar(50) NOT NULL,
    `email` varchar(100) NOT NULL,
    `password` varchar(255) NOT NULL,
    `created_at` datetime DEFAULT current_timestamp(),
    `account_status` varchar(20) DEFAULT 'active',
    `inactive_until` datetime DEFAULT NULL,
    `active_for_leads` tinyint(1) DEFAULT 1
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

