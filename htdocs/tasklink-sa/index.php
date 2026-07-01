<?php
session_start();
require_once 'includes/db.php';

$query = "
    SELECT s.*, 
           u.full_name, 
           u.profile_picture,
           c.category_name,
           COALESCE(s.service_picture, p.service_picture, '') as service_picture
    FROM services s
    JOIN users u ON s.provider_id = u.user_id
    JOIN categories c ON s.category_id = c.category_id
    LEFT JOIN provider_profiles p ON s.provider_id = p.provider_id
    ORDER BY s.created_at DESC
    LIMIT 6
";
$services = $pdo->query($query)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskLink SA – Local Trusted Services</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css">
    <style>
        .hero { background: linear-gradient(135deg, #1B6B3A 0%, #124E29 100%); color: white; padding: 80px 20px; text-align: center; }
        .hero .container { max-width: 700px; margin: 0 auto; }
        .hero h1 { font-size: 42px; margin-bottom: 16px; line-height: 1.2; }
        .hero p { font-size: 18px; margin-bottom: 30px; opacity: 0.9; }
        .search-bar { max-width: 600px; margin: 0 auto; display: flex; gap: 10px; background: white; padding: 10px; border-radius: 50px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .search-bar input { flex: 1; border: none; padding: 10px 20px; font-size: 16px; outline: none; border-radius: 50px; }
        .search-bar button { background: #1B6B3A; color: white; border: none; padding: 12px 28px; font-size: 16px; font-weight: 600; border-radius: 50px; cursor: pointer; }
        .search-bar button:hover { background: #124E29; }
        
        .section-title { margin: 40px 0 20px 0; font-size: 28px; font-weight: 700; color: #2D3748; }
        .services-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 30px; margin-bottom: 50px; }
        
        .service-card { background: white; border: 1px solid #E2E8F0; border-radius: 16px; overflow: hidden; transition: transform 0.2s, box-shadow 0.2s; display: flex; flex-direction: column; }
        .service-card:hover { transform: translateY(-4px); box-shadow: 0 10px 20px rgba(0,0,0,0.05); }
        .service-image-placeholder { height: 180px; background: #E8F5EE; display: flex; align-items: center; justify-content: center; font-size: 48px; overflow: hidden; }
        .service-image-placeholder img { width: 100%; height: 100%; object-fit: cover; }
        
        .service-content { padding: 20px; flex: 1; display: flex; flex-direction: column; }
        .service-category { font-size: 12px; font-weight: 600; text-transform: uppercase; color: #1B6B3A; margin-bottom: 8px; }
        .service-title { font-size: 18px; font-weight: 700; color: #2D3748; margin-bottom: 10px; text-decoration: none; }
        .service-title:hover { color: #1B6B3A; }
        .service-provider { display: flex; align-items: center; gap: 10px; margin-top: auto; padding-top: 15px; border-top: 1px solid #EDF2F7; }
        
        .provider-avatar { width: 36px; height: 36px; border-radius: 50%; background: #1B6B3A; color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px; overflow: hidden; }
        .provider-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .provider-name { font-size: 14px; font-weight: 500; color: #4A5568; }
        .service-price { margin-left: auto; text-align: right; }
        .price-val { font-size: 16px; font-weight: 700; color: #2D3748; }
        .price-lbl { font-size: 11px; color: #718096; }
    </style>
</head>
<body>

<?php include 'includes/header.php'; ?>

<div class="hero">
    <div class="container">
        <h1>Find Trusted Local Services in South Africa</h1>
        <p>From plumbers to developers, book reliable independent professionals instantly.</p>
        
        <form action="/browse.php" method="GET" class="search-bar">
            <input type="text" name="query" placeholder="What service do you need today? (e.g., Car Wash, Plumber)">
            <button type="submit">Search</button>
        </form>
    </div>
</div>

<div class="container">
    <h2 class="section-title">Featured Services</h2>
    
    <div class="services-grid">
        <?php foreach ($services as $service): ?>
            <div class="service-card">
                <div class="service-image-placeholder">
                    <?php if (!empty($service['service_picture'])): ?>
                        <img src="/<?php echo htmlspecialchars($service['service_picture']); ?>" alt="Service Image">
                    <?php else: ?>
                        🛠️
                    <?php endif; ?>
                </div>
                <div class="service-content">
                    <span class="service-category"><?php echo htmlspecialchars($service['category_name']); ?></span>
                    <a href="/service-detail.php?id=<?php echo $service['service_id']; ?>" class="service-title">
                        <?php echo htmlspecialchars($service['title']); ?>
                    </a>
                    
                    <div class="service-provider">
                        <div class="provider-avatar">
                            <?php if (!empty($service['profile_picture'])): ?>
                                <img src="/<?php echo htmlspecialchars($service['profile_picture']); ?>" alt="Provider Image">
                            <?php else: ?>
                                <?php echo strtoupper(substr($service['full_name'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <span class="provider-name"><?php echo htmlspecialchars($service['full_name']); ?></span>
                        
                        <div class="service-price">
                            <div class="price-val">R<?php echo htmlspecialchars($service['price']); ?></div>
                            <div class="price-lbl">per hour</div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

</body>
</html>