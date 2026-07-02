<?php
/**
 * Excel Export Endpoint Alias
 * Bypass WAF rules on free hosting platforms (ProFreeHost/ByetHost) that block filenames starting with admin_
 */
require_once __DIR__ . '/admin_export_excel.php';
