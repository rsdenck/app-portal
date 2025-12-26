Name:           portal-app
Version:        1.0.0
Release:        1%{?dist}
Summary:        Portal - Datacenter and Virtualization Management System

License:        Proprietary
URL:            http://portal.local
Source0:        %{name}-%{version}.tar.gz

BuildArch:      noarch
Requires:       nginx, mariadb-server, php-fpm, php-mysqlnd, php-xml, php-curl, php-mbstring, php-gd

%description
Portal provides a unified interface for managing vCenter, NSX, and other datacenter infrastructure.

%prep
%setup -q

%install
rm -rf $RPM_BUILD_ROOT
mkdir -p $RPM_BUILD_ROOT/var/www/portal
cp -r * $RPM_BUILD_ROOT/var/www/portal/

%files
/var/www/portal

%post
# Run installation script
bash /var/www/portal/scripts/install/install.sh

%changelog
* Thu Dec 25 2025 Portal Dev Team - 1.0.0-1
- Initial release
