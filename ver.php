<?php
try {
    $serverName = "localhost,1433"; // 服务器,端口
    $database = "TopGun";
    $username = "sa";
    $password = "vGHBuyrmFYDHv4cHkhdX！";
    
    $dsn = "sqlsrv:Server=$serverName;Database=$database";
    $conn = new PDO($dsn, $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "连接成功！\n";
    
    // 执行简单查询
    $sql = "SELECT name FROM sys.databases";
    $stmt = $conn->query($sql);
    
    echo "可用数据库:\n";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "- " . $row['name'] . "\n";
    }
    
} catch (PDOException $e) {
    die("错误: " . $e->getMessage());
}
?>