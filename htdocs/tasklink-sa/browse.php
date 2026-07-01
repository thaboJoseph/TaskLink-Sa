<?php
session_start();
require_once 'includes/db.php';

// Get filters from URL
$search     = trim($_GET['search'] ?? '');
$category   = (int)($_GET['category'] ?? 0);
$max_price  = (int)($_GET['max_price'] ?? 10000); // Updated default to 10000

// Build query dynamically
$where  = ['1=1'];
$params = [];

if (!empty($search)) {
    $where[]  = "(s.title LIKE ? OR s.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category > 0) {
    $where[]  = "s.category_id = ?";
    $params[] = $category;
}

if ($max_price > 0) {
    $where[]  = "s.price <= ?";
    $params[] = $max_price;
}

$whereStr = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT s.*, 
           COALESCE(c.category_name, 'Uncategorized') as category_name,
           u.full_name as provider_name,
           COALESCE(p.rating, 0) as rating,
           COALESCE(p.total_reviews, 0) as total_reviews,
           COALESCE(s.service_picture, p.service_picture, '') as service_picture
    FROM services s
    LEFT JOIN categories c ON s.category_id = c.category_id
    JOIN users u ON s.provider_id = u.user_id
    LEFT JOIN provider_profiles p 
        ON s.provider_id = p.provider_id
    WHERE $whereStr
    ORDER BY s.created_at DESC
");
$stmt->execute($params);
$services = $stmt->fetchAll();

// Get ALL categories from the categories table
$categories = $pdo->query("
    SELECT * FROM categories 
    ORDER BY category_name ASC
")->fetchAll();
?>
<?php require_once 'includes/header.php'; ?>

<div style="display:grid; 
            grid-template-columns:280px 1fr; 
            min-height:calc(100vh - 64px);">

    <!-- IMPROVED FILTER SIDEBAR -->
    <div style="background:white; 
                padding:28px 24px; 
                border-right:1px solid #E2E8F0;
                position:sticky; top:64px; 
                height:calc(100vh - 64px); 
                overflow-y:auto;
                box-shadow: 2px 0 8px rgba(0,0,0,0.03);">

        <div style="margin-bottom:24px;">
            <div style="display:flex; align-items:center; gap:10px; margin-bottom:8px;">
                <span style="font-size:22px;">🔍</span>
                <h3 style="font-size:18px; font-weight:700; color:#1A202C; margin:0;">
                    Filter Services
                </h3>
            </div>
            <p style="font-size:13px; color:#718096; margin:0;">
                Find the perfect service for your needs
            </p>
        </div>

        <form method="GET" action="">

            <!-- Search -->
            <div class="form-group" style="margin-bottom:20px;">
                <label style="font-weight:600; color:#2D3748; display:block; margin-bottom:6px;">Search</label>
                <input type="text" name="search"
                       placeholder="e.g. plumber, borehole, cleaning..."
                       value="<?php echo htmlspecialchars($search); ?>"
                       style="width:100%; padding:12px 14px; border:1.5px solid #E2E8F0; border-radius:10px; font-size:14px;">
            </div>

            <!-- Max Budget Slider -->
            <div class="form-group" style="margin-bottom:24px;">
                <label style="font-weight:600; color:#2D3748; display:block; margin-bottom:8px;">
                    Max Budget 
                    <span id="sliderValue" 
                          style="color:#1B6B3A; font-weight:700; background:#E8F5EE; padding:2px 10px; border-radius:999px; font-size:13px;">
                        R<?php echo number_format($max_price); ?>
                    </span>
                </label>
                <input type="range" 
                       id="priceSlider"
                       name="max_price"
                       min="50" max="10000" 
                       step="50"
                       value="<?php echo $max_price; ?>"
                       style="width:100%; accent-color:#1B6B3A; height:6px;">
                <div style="display:flex; justify-content:space-between; font-size:11px; color:#A0AEC0; margin-top:4px;">
                    <span>R50</span>
                    <span>R10,000</span>
                </div>
            </div>

            <!-- Category -->
            <div class="form-group" style="margin-bottom:24px;">
                <label style="font-weight:600; color:#2D3748; display:block; margin-bottom:6px;">Category</label>
                <select name="category" style="width:100%; padding:12px 14px; border:1.5px solid #E2E8F0; border-radius:10px; font-size:14px; background:white;">
                    <option value="0">All Categories</option>
                    <?php foreach($categories as $cat): ?>
                        <option value="<?php echo $cat['category_id']; ?>"
                            <?php echo $category == $cat['category_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['category_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Buttons -->
            <button type="submit" 
                    class="btn-primary"
                    style="width:100%; padding:13px; font-size:15px; font-weight:600; margin-bottom:10px;">
                Apply Filters
            </button>

            <?php if(!empty($search) || $category > 0 || $max_price < 10000): ?>
                <a href="/browse.php"
                   class="btn-secondary"
                   style="width:100%; padding:12px; text-align:center; display:block; font-size:14px;">
                    Clear All Filters
                </a>
            <?php endif; ?>

        </form>
    </div>

    <!-- MAIN CONTENT (unchanged) -->
    <div style="padding:32px 40px;">

        <div style="display:flex; 
                    justify-content:space-between; 
                    align-items:center; 
                    margin-bottom:24px;">
            <div>
                <h2 style="font-size:20px; 
                           font-weight:700; 
                           color:#1A202C;">
                    <?php if(!empty($search)): ?>
                        Results for "<?php echo htmlspecialchars($search); ?>"
                    <?php elseif($category > 0): ?>
                        <?php 
                        foreach($categories as $cat) {
                            if($cat['category_id'] == $category) {
                                echo htmlspecialchars($cat['category_name']);
                            }
                        }
                        ?>
                    <?php else: ?>
                        All Services
                    <?php endif; ?>
                </h2>
                <p style="font-size:13px; color:#4A5568; margin-top:4px;">
                    <?php echo count($services); ?> service(s) found
                </p>
            </div>
        </div>

        <!-- Category Badges -->
        <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:24px;">
            <a href="/browse.php"
               class="badge"
               style="padding:8px 16px; font-size:12px;
                      <?php echo $category === 0 ? 
                      'background:#1B6B3A;color:white;' : ''; ?>">
                All
            </a>
            <?php foreach($categories as $cat): ?>
                <?php
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE category_id = ?");
                $countStmt->execute([$cat['category_id']]);
                $catCount = $countStmt->fetchColumn();
                ?>
                <a href="/browse.php?category=<?php echo $cat['category_id']; ?>"
                   class="badge"
                   style="padding:8px 14px; font-size:12px; text-decoration:none; display:flex; align-items:center; gap:6px;
                          <?php echo $category == $cat['category_id'] ? 
                          'background:#1B6B3A;color:white;' : ''; ?>">
                    <?php echo htmlspecialchars($cat['category_name']); ?>
                    <span style="background:<?php echo $category == $cat['category_id'] ? 'rgba(255,255,255,0.3)' : '#E2E8F0'; ?>; 
                                 color:<?php echo $category == $cat['category_id'] ? 'white' : '#4A5568'; ?>; 
                                 padding:1px 7px; border-radius:999px; font-size:11px; font-weight:600;">
                        <?php echo $catCount; ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if(empty($services)): ?>
            <div style="text-align:center; 
                        padding:80px 20px; 
                        color:#4A5568;">
                <p style="font-size:48px;">🔍</p>
                <p style="font-size:18px; 
                          font-weight:600; 
                          margin-top:16px;">
                    No services found
                </p>
                <p style="margin-top:8px;">
                    Try adjusting your filters 
                    or search term
                </p>
                <a href="/browse.php"
                   class="btn-primary"
                   style="margin-top:24px; 
                          display:inline-block; 
                          padding:12px 32px;">
                    View All Services
                </a>
            </div>
        <?php else: ?>
            <div class="services-grid">
                <?php foreach($services as $service): ?>
                <div class="service-card-wrapper">
                    <a href="/service-detail.php?id=<?php echo $service['service_id']; ?>" 
                       style="text-decoration: none; color: inherit; display: block; height: 100%;">
                        <div class="service-card"
                             data-price="<?php echo $service['price']; ?>"
                             style="height: 100%; display: flex; flex-direction: column; justify-content: space-between; transition: transform 0.2s, box-shadow 0.2s; cursor: pointer;"
                             onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 10px 15px -3px rgba(0,0,0,0.1)';"
                             onmouseout="this.style.transform='none'; this.style.boxShadow='none';">
                            
                            <div>
                                <!-- SERVICE IMAGE -->
                                <div class="service-card-img" style="background:#E8F5EE; overflow:hidden; position:relative; height:180px;">
                                    <?php 
                                    $imgPath = $service['service_picture'];
                                    
                                    if (!empty($imgPath)): 
                                        $fullServerPath = __DIR__ . '/' . $imgPath;
                                        if (file_exists($fullServerPath)): 
                                    ?>
                                        <img src="/<?php echo htmlspecialchars($imgPath); ?>" 
                                             style="width:100%; height:100%; object-fit:cover; display:block;"
                                             alt="<?php echo htmlspecialchars($service['title']); ?>">
                                    <?php else: ?>
                                        <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; font-size:48px; color:#1B6B3A;">
                                            🛠️
                                        </div>
                                    <?php endif; ?>
                                    <?php else: ?>
                                        <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; font-size:48px; color:#1B6B3A;">
                                            🛠️
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="service-card-body">
                                    <h3>
                                        <?php echo htmlspecialchars($service['title']); ?>
                                    </h3>
                                    <p class="provider">
                                        <?php echo htmlspecialchars($service['provider_name']); ?>
                                    </p>
                                    <p class="price">
                                        R<?php echo number_format($service['price'], 2); ?>
                                        <?php echo $service['price_type'] === 'hourly' ? '/hr' : ' fixed'; ?>
                                    </p>
                                    <p class="rating">
                                        ⭐ <?php echo number_format($service['rating'], 1); ?>
                                        <span style="color:#A0AEC0;">
                                            (<?php echo $service['total_reviews']; ?> reviews)
                                        </span>
                                    </p>
                                </div>
                            </div>

                            <div class="service-card-footer" style="display: flex; justify-content: space-between; align-items: center; margin-top: 12px;">
                                <span class="badge">
                                    <?php echo htmlspecialchars($service['category_name']); ?>
                                </span>
                                <span class="btn-primary"
                                      style="padding:8px 16px; 
                                             font-size:13px;
                                             display: inline-block;">
                                    Book
                                </span>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
// Live update for price slider
const slider = document.getElementById('priceSlider');
const valueDisplay = document.getElementById('sliderValue');

if (slider && valueDisplay) {
    slider.addEventListener('input', function() {
        valueDisplay.textContent = 'R' + parseInt(this.value).toLocaleString();
    });
}
</script>

<?php require_once 'includes/footer.php'; ?>