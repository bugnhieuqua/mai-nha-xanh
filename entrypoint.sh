#!/bin/bash
set -e

# Táº¯t cÃ¡c MPM xung Ä‘á»™t ngay khi container khá»Ÿi Ä‘á»™ng (runtime)
a2dismod mpm_event || true
a2dismod mpm_worker || true
a2enmod mpm_prefork || true

# Thá»±c thi lá»‡nh chÃ­nh cá»§a container (apache2-foreground)
exec "$@"
