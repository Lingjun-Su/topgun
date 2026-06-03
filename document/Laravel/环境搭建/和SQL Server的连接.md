# 和SQL Server的连接
1.从微软网站上下载pdo驱动。
2.修改php.ini文件
; 确保以下扩展已启用
extension=php_pdo_sqlsrv.dll  ; Windows
extension=php_sqlsrv.dll      ; Windows

; 或对于Linux
extension=pdo_sqlsrv.so
extension=sqlsrv.so

3.把对应的dll文件复制到\php\ext文件夹中

4.修改.env文件
DB_CONNECTION=sqlsrv
DB_HOST=127.0.0.1,1433
DB_PORT=1433
DB_DATABASE=TopGun
DB_USERNAME=sa
DB_PASSWORD="vGHBuyrmFYDHv4cHkhdX！"//需要有双引号

5.打开SQL Server 配置管理器（SQL Server Configuration Manager）
如果找不到就直接运行
SQL Server 2022: 输入 SQLServerManager16.msc
SQL Server 2019: 输入 SQLServerManager15.msc
SQL Server 2017: 输入 SQLServerManager14.msc
SQL Server 2016: 输入 SQLServerManager13.msc

把SQL Server 网络配置把TCP/IP启用