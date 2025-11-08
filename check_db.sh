#!/bin/bash
mysql -u wgs-service_cz -p'BwDkX-fJBMmH' wgs_service_cz << 'SQL'
DESCRIBE wgs_photos;
SELECT * FROM wgs_photos ORDER BY created_at DESC LIMIT 3;
SQL
