<?php
session_start();
include 'db.php';

// Function to fetch a news article by ID
function fetchNewsById($conn, $id) {
    $sql = "SELECT n.id, n.title, n.content, n.image, n.author, n.created_at, n.views, n.comments, n.read_time, n.shares, c.name AS category_name 
            FROM news n LEFT JOIN categories c ON n.category_id = c.id 
            WHERE n.id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        die("Database query error.");
    }
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Function to fetch recent news for sidebar
function fetchRecentNews($conn, $limit, $exclude_id) {
    $sql = "SELECT n.id, n.title, n.image, n.author, n.created_at, c.name AS category_name 
            FROM news n LEFT JOIN categories c ON n.category_id = c.id 
            WHERE n.id != ? 
            ORDER BY n.created_at DESC 
            LIMIT ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        die("Database query error.");
    }
    $stmt->bind_param("ii", $exclude_id, $limit);
    $stmt->execute();
    return $stmt->get_result();
}

// Function to fetch comments for a news article
function fetchComments($conn, $news_id) {
    $sql = "SELECT c.name, c.comment, c.created_at 
            FROM comments c 
            WHERE c.news_id = ? AND c.status = 'approved' 
            ORDER BY c.created_at DESC";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        die("Database query error.");
    }
    $stmt->bind_param("i", $news_id);
    $stmt->execute();
    return $stmt->get_result();
}

// Function to save a comment
function saveComment($conn, $news_id, $user_id, $name, $email, $comment) {
    $sql = "INSERT INTO comments (news_id, user_id, name, email, comment, status) 
            VALUES (?, ?, ?, ?, ?, 'pending')";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    $stmt->bind_param("iisss", $news_id, $user_id, $name, $email, $comment);
    if ($stmt->execute()) {
        $sql = "UPDATE news SET comments = comments + 1 WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $news_id);
        $stmt->execute();
        return true;
    }
    return false;
}

// Check database connection
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Get news ID from URL and increment views
$news_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$article = fetchNewsById($conn, $news_id);
if ($article) {
    $sql = "UPDATE news SET views = views + 1 WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $news_id);
    $stmt->execute();
}

// Handle comment submission
$comment_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['name'], $_POST['email'], $_POST['comment'])) {
    if (!isset($_SESSION['user_id'])) {
        $comment_error = "You must be logged in to comment.";
    } else {
        $user_id = $_SESSION['user_id'];
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $comment = trim($_POST['comment']);
        if (empty($name) || empty($email) || empty($comment)) {
            $comment_error = "All fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $comment_error = "Invalid email address.";
        } elseif (strlen($name) > 100 || strlen($email) > 100 || strlen($comment) > 1000) {
            $comment_error = "Input exceeds maximum length.";
        } else {
            if (saveComment($conn, $news_id, $user_id, $name, $email, $comment)) {
                header("Location: detail-page.php?id=$news_id#comments");
                exit;
            } else {
                $comment_error = "Failed to save comment. Please try again.";
            }
        }
    }
}

// Fetch comments
$comments = fetchComments($conn, $news_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Newsers - <?php echo $article ? htmlspecialchars($article['title']) : 'Article Not Found'; ?></title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta content="News, Magazine, Articles" name="keywords">
    <meta content="<?php echo $article ? htmlspecialchars(mb_strimwidth($article['content'], 0, 150, '...')) : ''; ?>" name="description">

    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600&family=Raleway:wght@100;600;800&display=swap" rel="stylesheet"> 

    <!-- Icon Font Stylesheet -->
    <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.15.4/css/all.css"/>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Libraries Stylesheet -->
    <link href="lib/animate/animate.min.css" rel="stylesheet">
    <link href="lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">

    <!-- Customized Bootstrap Stylesheet -->
    <link href="css/bootstrap.min.css" rel="stylesheet">

    <!-- Template Stylesheet -->
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Spinner Start -->
    <div id="spinner" class="show w-100 vh-100 bg-white position-fixed translate-middle top-50 start-50 d-flex align-items-center justify-content-center">
        <div class="spinner-grow text-primary" role="status"></div>
    </div>
    <!-- Spinner End -->

    <!-- Navbar Start -->
    <div class="container-fluid sticky-top px-0">
        <div class="container-fluid topbar bg-dark d-none d-lg-block">
            <div class="container px-0">
                <div class="topbar-top d-flex justify-content-between flex-lg-wrap">
                    <div class="top-info flex-grow-0">
                        <span class="rounded-circle btn-sm-square bg-primary me-2">
                            <i class="fas fa-bolt text-white"></i>
                        </span>
                        <div class="pe-2 me-3 border-end border-white d-flex align-items-center">
                            <p class="mb-0 text-white fs-6 fw-normal">Trending</p>
                        </div>
                        <div class="overflow-hidden" style="width: 735px;">
                            <div id="note" class="ps-2">
                                <img src="img/features-fashion.jpg" class="img-fluid rounded-circle border border-3 border-primary me-2" style="width: 30px; height: 30px;" alt="">
                                <?php
                                $trending = fetchRecentNews($conn, 1, $news_id);
                                if ($trending && $trend = $trending->fetch_assoc()):
                                ?>
                                <a href="detail-page.php?id=<?php echo $trend['id']; ?>"><p class="text-white mb-0 link-hover"><?php echo htmlspecialchars(mb_strimwidth($trend['title'], 0, 60, '...')); ?></p></a>
                                <?php else: ?>
                                <a href="#"><p class="text-white mb-0 link-hover">No trending news available.</p></a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="top-link flex-lg-wrap">
                        <i class="fas fa-calendar-alt text-white border-end border-secondary pe-2 me-2"> <span class="text-body"><?php echo date('l, M d, Y'); ?></span></i>
                        <div class="d-flex icon">
                            <p class="mb-0 text-white me-2">Follow Us:</p>
                            <a href="#" class="me-2"><i class="fab fa-facebook-f text-body link-hover"></i></a>
                            <a href="#" class="me-2"><i class="fab fa-twitter text-body link-hover"></i></a>
                            <a href="#" class="me-2"><i class="fab fa-instagram text-body link-hover"></i></a>
                            <a href="#" class="me-2"><i class="fab fa-youtube text-body link-hover"></i></a>
                            <a href="#" class="me-2"><i class="fab fa-linkedin-in text-body link-hover"></i></a>
                            <a href="#" class="me-2"><i class="fab fa-skype text-body link-hover"></i></a>
                            <a href="#" class=""><i class="fab fa-pinterest-p text-body link-hover"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="container-fluid bg-light">
            <div class="container px-0">
                <nav class="navbar navbar-light navbar-expand-xl">
                    <a href="index.php" class="navbar-brand mt-3">
                        <p class="text-primary display-6 mb-2" style="line-height: 0;">Newsers</p>
                        <small class="text-body fw-normal" style="letter-spacing: 12px;">Nespaper</small>
                    </a>
                    <button class="navbar-toggler py-2 px-3" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                        <span class="fa fa-bars text-primary"></span>
                    </button>
                    <div class="collapse navbar-collapse bg-light py-3" id="navbarCollapse">
                        <div class="navbar-nav mx-auto border-top">
                            <a href="index.php" class="nav-item nav-link">Home</a>
                            <a href="detail-page.php" class="nav-item nav-link active">Detail Page</a>
                            <a href="404.html" class="nav-item nav-link">404 Page</a>
                            <div class="nav-item dropdown">
                                <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown">Dropdown</a>
                                <div class="dropdown-menu m-0 bg-secondary rounded-0">
                                    <a href="#" class="dropdown-item">Dropdown 1</a>
                                    <a href="#" class="dropdown-item">Dropdown 2</a>
                                    <a href="#" class="dropdown-item">Dropdown 3</a>
                                    <a href="#" class="dropdown-item">Dropdown 4</a>
                                </div>
                            </div>
                            <a href="contact.html" class="nav-item nav-link">Contact Us</a>
                        </div>
                        <div class="d-flex flex-nowrap border-top pt-3 pt-xl-0">
                            <?php if (!isset($_SESSION['user_id'])): ?>
                            <a href="login.php" class="btn btn-primary me-2">Login</a>
                            <a href="register.php" class="btn btn-primary">Register</a>
                            <?php else: ?>
                            <span class="text-dark me-2">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                            <a href="logout.php" class="btn btn-primary">Logout</a>
                            <?php endif; ?>
                            <div class="d-flex">
                                <img src="img/weather-icon.png" class="img-fluid w-100 me-2" alt="Weather Icon">
                                <div class="d-flex align-items-center">
                                    <strong class="fs-4 text-secondary">31°C</strong>
                                    <div class="d-flex flex-column ms-2" style="width: 150px;">
                                        <span class="text-body">NEW YORK,</span>
                                        <small><?php echo date('M d, Y'); ?></small>
                                    </div>
                                </div>
                            </div>
                            <button class="btn-search btn border border-primary btn-md-square rounded-circle bg-white my-auto" data-bs-toggle="modal" data-bs-target="#searchModal"><i class="fas fa-search text-primary"></i></button>
                        </div>
                    </div>
                </nav>
            </div>
        </div>
    </div>
    <!-- Navbar End -->

    <!-- Modal Search Start -->
    <div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-fullscreen">
            <div class="modal-content rounded-0">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Search by keyword</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body d-flex align-items-center">
                    <form action="search.php" method="GET" class="input-group w-75 mx-auto d-flex">
                        <input type="search" name="q" class="form-control p-3" placeholder="keywords" aria-describedby="search-icon-1">
                        <button type="submit" class="btn btn-primary input-group-text p-3"><i class="fa fa-search text-white"></i></button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <!-- Modal Search End -->

    <!-- Single Product Start -->
    <div class="container-fluid py-5">
        <div class="container py-5">
            <ol class="breadcrumb justify-content-start mb-4">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="#">Pages</a></li>
                <li class="breadcrumb-item active text-dark">Single Page</li>
            </ol>
            <div class="row g-4">
                <div class="col-lg-8">
                    <?php if ($article): 
                        $image = file_exists("img/" . $article['image']) ? htmlspecialchars($article['image']) : 'default.jpg';
                    ?>
                    <div class="mb-4">
                        <a href="#" class="h1 display-5"><?php echo htmlspecialchars($article['title']); ?></a>
                    </div>
                    <div class="position-relative rounded overflow-hidden mb-3">
                        <img src="img/<?php echo $image; ?>" class="img-zoomin img-fluid rounded w-100" alt="<?php echo htmlspecialchars($article['title']); ?>">
                        <div class="position-absolute text-white px-4 py-2 bg-primary rounded" style="top: 20px; right: 20px;">
                            <?php echo htmlspecialchars($article['category_name'] ?: 'General'); ?>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between">
                        <a href="https://twitter.com/intent/tweet?url=<?php echo urlencode('http://yourdomain.com/detail-page.php?id=' . $news_id); ?>&text=<?php echo urlencode($article['title']); ?>" class="text-dark link-hover me-3"><i class="fab fa-twitter"></i> Tweet</a>
                        <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode('http://yourdomain.com/detail-page.php?id=' . $news_id); ?>" class="text-dark link-hover me-3"><i class="fab fa-facebook-f"></i> Share</a>
                        <a href="#" class="text-dark link-hover me-3"><i class="fa fa-clock"></i> <?php echo $article['read_time']; ?> minute read</a>
                        <a href="#" class="text-dark link-hover me-3"><i class="fa fa-eye"></i> <?php echo $article['views']; ?> View<?php echo $article['views'] == 1 ? '' : 's'; ?></a>
                        <a href="#" class="text-dark link-hover me-3"><i class="fa fa-comment-dots"></i> <?php echo $article['comments']; ?> Comment<?php echo $article['comments'] == 1 ? '' : 's'; ?></a>
                        <a href="#" class="text-dark link-hover share-btn" data-id="<?php echo $article['id']; ?>"><i class="fa fa-arrow-up"></i> <?php echo $article['shares']; ?> Share<?php echo $article['shares'] == 1 ? '' : 's'; ?></a>
                    </div>
                    <p class="my-4"><?php echo htmlspecialchars($article['content']); ?></p>
                    <?php else: ?>
                    <div class="mb-4">
                        <h1 class="display-5">Article Not Found</h1>
                        <p>The requested article could not be found. Please check the URL or return to the <a href="index.php">homepage</a>.</p>
                    </div>
                    <?php endif; ?>
                    <div class="bg-light rounded my-4 p-4">
                        <h4 class="mb-4">You Might Also Like</h4>
                        <div class="row g-4">
                            <?php
                            $relatedNews = fetchRecentNews($conn, 2, $news_id);
                            if ($relatedNews && $relatedNews->num_rows > 0):
                                while ($related = $relatedNews->fetch_assoc()):
                                    $relatedImage = file_exists("img/" . $related['image']) ? htmlspecialchars($related['image']) : 'default.jpg';
                            ?>
                            <div class="col-lg-6">
                                <div class="d-flex align-items-center p-3 bg-white rounded">
                                    <img src="img/<?php echo $relatedImage; ?>" class="img-fluid rounded" alt="<?php echo htmlspecialchars($related['title']); ?>">
                                    <div class="ms-3">
                                        <a href="detail-page.php?id=<?php echo $related['id']; ?>" class="h5 mb-2"><?php echo htmlspecialchars($related['title']); ?></a>
                                        <p class="text-dark mt-3 mb-0 me-3"><i class="fa fa-clock"></i> <?php echo $related['read_time'] ?? 5; ?> minute read</p>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; else: ?>
                            <p>No related articles available.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div id="comments" class="bg-light rounded p-4">
                        <h4 class="mb-4">Comments</h4>
                        <?php if ($comments && $comments->num_rows > 0): 
                            while ($comment = $comments->fetch_assoc()):
                        ?>
                        <div class="p-4 bg-white rounded mb-4">
                            <div class="row g-4">
                                <div class="col-3">
                                    <img src="img/footer-4.jpg" class="img-fluid rounded-circle w-100" alt="Commenter">
                                </div>
                                <div class="col-9">
                                    <div class="d-flex justify-content-between">
                                        <h5><?php echo htmlspecialchars($comment['name']); ?></h5>
                                        <a href="#" class="link-hover text-body fs-6"><i class="fas fa-long-arrow-alt-right me-1"></i> Reply</a>
                                    </div>
                                    <small class="text-body d-block mb-3"><i class="fas fa-calendar-alt me-1"></i> <?php echo date('M d, Y', strtotime($comment['created_at'])); ?></small>
                                    <p class="mb-0"><?php echo htmlspecialchars($comment['comment']); ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; else: ?>
                        <p>No comments yet. Be the first to comment!</p>
                        <?php endif; ?>
                    </div>
                    <div class="bg-light rounded p-4 my-4">
                        <h4 class="mb-4">Leave A Comment</h4>
                        <?php if ($comment_error): ?>
                        <p class="text-danger"><?php echo htmlspecialchars($comment_error); ?></p>
                        <?php endif; ?>
                        <?php if (!isset($_SESSION['user_id'])): ?>
                        <p>Please <a href="login.php">log in</a> to leave a comment.</p>
                        <?php else: ?>
                        <form action="detail-page.php?id=<?php echo $news_id; ?>#comments" method="POST">
                            <div class="row g-4">
                                <div class="col-lg-6">
                                    <input type="text" name="name" class="form-control py-3" placeholder="Full Name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : htmlspecialchars($_SESSION['username']); ?>">
                                </div>
                                <div class="col-lg-6">
                                    <input type="email" name="email" class="form-control py-3" placeholder="Email Address" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                </div>
                                <div class="col-12">
                                    <textarea class="form-control" name="comment" cols="30" rows="7" placeholder="Write Your Comment Here"><?php echo isset($_POST['comment']) ? htmlspecialchars($_POST['comment']) : ''; ?></textarea>
                                </div>
                                <div class="col-12">
                                    <button class="form-control btn btn-primary py-3" type="submit">Submit Now</button>
                                </div>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="row g-4">
                        <div class="col-12">
                            <div class="p-3 rounded border">
                                <div class="input-group w-100 mx-auto d-flex mb-4">
                                    <form action="search.php" method="GET" class="w-100 d-flex">
                                        <input type="search" name="q" class="form-control p-3" placeholder="keywords" aria-describedby="search-icon-1">
                                        <button type="submit" class="btn btn-primary input-group-text p-3"><i class="fa fa-search text-white"></i></button>
                                    </form>
                                </div>
                                <h4 class="mb-4">Popular Categories</h4>
                                <div class="row g-2">
                                    <?php
                                    $catResult = $conn->query("SELECT id, name FROM categories ORDER BY name");
                                    if ($catResult && $catResult->num_rows > 0):
                                        while ($cat = $catResult->fetch_assoc()):
                                    ?>
                                    <div class="col-12">
                                        <a href="search.php?category_id=<?php echo $cat['id']; ?>" class="link-hover btn btn-light w-100 rounded text-uppercase text-dark py-3"><?php echo htmlspecialchars($cat['name']); ?></a>
                                    </div>
                                    <?php endwhile; else: ?>
                                    <p>No categories available.</p>
                                    <?php endif; ?>
                                </div>
                                <h4 class="my-4">Stay Connected</h4>
                                <div class="row g-4">
                                    <div class="col-12">
                                        <a href="#" class="w-100 rounded btn btn-primary d-flex align-items-center p-3 mb-2">
                                            <i class="fab fa-facebook-f btn btn-light btn-square rounded-circle me-3"></i>
                                            <span class="text-white">13,977 Fans</span>
                                        </a>
                                        <a href="#" class="w-100 rounded btn btn-danger d-flex align-items-center p-3 mb-2">
                                            <i class="fab fa-twitter btn btn-light btn-square rounded-circle me-3"></i>
                                            <span class="text-white">21,798 Follower</span>
                                        </a>
                                        <a href="#" class="w-100 rounded btn btn-warning d-flex align-items-center p-3 mb-2">
                                            <i class="fab fa-youtube btn btn-light btn-square rounded-circle me-3"></i>
                                            <span class="text-white">7,999 Subscriber</span>
                                        </a>
                                        <a href="#" class="w-100 rounded btn btn-dark d-flex align-items-center p-3 mb-2">
                                            <i class="fab fa-instagram btn btn-light btn-square rounded-circle me-3"></i>
                                            <span class="text-white">19,764 Follower</span>
                                        </a>
                                        <a href="#" class="w-100 rounded btn btn-secondary d-flex align-items-center p-3 mb-2">
                                            <i class="bi bi-cloud btn btn-light btn-square rounded-circle me-3"></i>
                                            <span class="text-white">31,999 Subscriber</span>
                                        </a>
                                        <a href="#" class="w-100 rounded btn btn-warning d-flex align-items-center p-3 mb-4">
                                            <i class="fab fa-dribbble btn btn-light btn-square rounded-circle me-3"></i>
                                            <span class="text-white">37,999 Subscriber</span>
                                        </a>
                                    </div>
                                </div>
                                <h4 class="my-4">Popular News</h4>
                                <div class="row g-4">
                                    <?php
                                    $popularNews = fetchRecentNews($conn, 4, $news_id);
                                    if ($popularNews && $popularNews->num_rows > 0):
                                        while ($pop = $popularNews->fetch_assoc()):
                                            $popImage = file_exists("img/" . $pop['image']) ? htmlspecialchars($pop['image']) : 'default.jpg';
                                    ?>
                                    <div class="col-12">
                                        <div class="row g-4 align-items-center features-item">
                                            <div class="col-4">
                                                <div class="rounded-circle position-relative">
                                                    <div class="overflow-hidden rounded-circle">
                                                        <img src="img/<?php echo $popImage; ?>" class="img-zoomin img-fluid rounded-circle w-100" alt="<?php echo htmlspecialchars($pop['title']); ?>">
                                                    </div>
                                                    <span class="rounded-circle border border-2 border-white bg-primary btn-sm-square text-white position-absolute" style="top: 10%; right: -10px;">3</span>
                                                </div>
                                            </div>
                                            <div class="col-8">
                                                <div class="features-content d-flex flex-column">
                                                    <p class="text-uppercase mb-2"><?php echo htmlspecialchars($pop['category_name'] ?: 'General'); ?></p>
                                                    <a href="detail-page.php?id=<?php echo $pop['id']; ?>" class="h6"><?php echo htmlspecialchars($pop['title']); ?></a>
                                                    <small class="text-body d-block"><i class="fas fa-calendar-alt me-1"></i> <?php echo date('M d, Y', strtotime($pop['created_at'])); ?></small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endwhile; else: ?>
                                    <div class="col-12">
                                        <p>No popular news available.</p>
                                    </div>
                                    <?php endif; ?>
                                    <div class="col-lg-12">
                                        <a href="index.php" class="link-hover btn border border-primary rounded-pill text-dark w-100 py-3 mb-4">View More</a>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="border-bottom my-3 pb-3">
                                            <h4 class="mb-0">Trending Tags</h4>
                                        </div>
                                        <ul class="nav nav-pills d-inline-flex text-center mb-4">
                                            <?php
                                            $tags = ['Lifestyle', 'Sports', 'Politics', 'Magazine', 'Game', 'Movie', 'Travel', 'World'];
                                            foreach ($tags as $tag):
                                            ?>
                                            <li class="nav-item mb-3">
                                                <a class="d-flex py-2 bg-light rounded-pill me-2" href="#"><span class="text-dark link-hover" style="width: 90px;"><?php echo htmlspecialchars($tag); ?></span></a>
                                            </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <div class="col-lg-12">
                                        <div class="position-relative banner-2">
                                            <img src="img/banner-2.jpg" class="img-fluid w-100 rounded" alt="Banner">
                                            <div class="text-center banner-content-2">
                                                <h6 class="mb-2">The Most Popular</h6>
                                                <p class="text-white mb-2">News & Magazine WP Theme</p>
                                                <a href="#" class="btn btn-primary text-white px-4">Shop Now</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Single Product End -->

        <!-- Footer Start -->
        <div class="container-fluid bg-dark footer py-5">
            <div class="container py-5">
                <div class="pb-4 mb-4" style="border-bottom: 1px solid rgba(255, 255, 255, 0.2);">
                    <div class="row g-4">
                        <div class="col-lg-3">
                            <a href="index.php" class="d-flex flex-column flex-wrap">
                                <p class="text-white mb-0 display-6">Newsers</p>
                                <small class="text-light" style="letter-spacing: 11px; line-height: 0;">Newspaper</small>
                            </a>
                        </div>
                        <div class="col-lg-9">
                            <form action="subscribe.php" method="POST" class="d-flex position-relative rounded-pill overflow-hidden">
                                <input class="form-control border-0 w-100 py-3 rounded-pill" type="email" name="email" placeholder="example@gmail.com" required>
                                <button type="submit" class="btn btn-primary border-0 py-3 px-5 rounded-pill text-white position-absolute" style="top: 0; right: 0;">Subscribe Now</button>
                            </form>
                            <?php if (isset($_GET['subscribed'])): ?>
                            <p class="text-success mt-2">Subscription successful!</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="row g-5">
                    <div class="col-lg-6 col-xl-3">
                        <div class="footer-item-1">
                            <h4 class="mb-4 text-white">Get In Touch</h4>
                            <p class="text-secondary line-h">Address: <span class="text-white">123 Street, New York</span></p>
                            <p class="text-secondary line-h">Email: <span class="text-white">info@newsers.com</span></p>
                            <p class="text-secondary line-h">Phone: <span class="text-white">+0123 4567 8910</span></p>
                            <div class="d-flex line-h">
                                <a class="btn btn-light me-2 btn-md-square rounded-circle" href="#"><i class="fab fa-twitter text-dark"></i></a>
                                <a class="btn btn-light me-2 btn-md-square rounded-circle" href="#"><i class="fab fa-facebook-f text-dark"></i></a>
                                <a class="btn btn-light me-2 btn-md-square rounded-circle" href="#"><i class="fab fa-youtube text-dark"></i></a>
                                <a class="btn btn-light btn-md-square rounded-circle" href="#"><i class="fab fa-linkedin-in text-dark"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-xl-3">
                        <div class="footer-item-2">
                            <div class="d-flex flex-column mb-4">
                                <h4 class="mb-4 text-white">Recent Posts</h4>
                                <?php
                                $recentPosts = fetchRecentNews($conn, 2, $news_id);
                                if ($recentPosts && $recentPosts->num_rows > 0):
                                    while ($post = $recentPosts->fetch_assoc()):
                                        $postImage = file_exists("img/" . $post['image']) ? htmlspecialchars($post['image']) : 'default.jpg';
                                ?>
                                <a href="detail-page.php?id=<?php echo $post['id']; ?>">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle border border-2 border-primary overflow-hidden">
                                            <img src="img/<?php echo $postImage; ?>" class="img-zoomin img-fluid rounded-circle w-100" alt="<?php echo htmlspecialchars($post['title']); ?>">
                                        </div>
                                        <div class="d-flex flex-column ps-4">
                                            <p class="text-uppercase text-white mb-3"><?php echo htmlspecialchars($post['category_name'] ?: 'General'); ?></p>
                                            <a href="detail-page.php?id=<?php echo $post['id']; ?>" class="h6 text-white"><?php echo htmlspecialchars($post['title']); ?></a>
                                            <small class="text-white d-block"><i class="fas fa-calendar-alt me-1"></i> <?php echo date('M d, Y', strtotime($post['created_at'])); ?></small>
                                        </div>
                                    </div>
                                </a>
                                <?php endwhile; else: ?>
                                <p class="text-white">No recent posts available.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-xl-3">
                        <div class="d-flex flex-column text-start footer-item-3">
                            <h4 class="mb-4 text-white">Categories</h4>
                            <?php
                            $catResult->data_seek(0);
                            while ($cat = $catResult->fetch_assoc()):
                            ?>
                            <a class="btn-link text-white" href="search.php?category_id=<?php echo $cat['id']; ?>"><i class="fas fa-angle-right text-white me-2"></i> <?php echo htmlspecialchars($cat['name']); ?></a>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    <div class="col-lg-6 col-xl-3">
                        <div class="footer-item-4">
                            <h4 class="mb-4 text-white">Our Gallery</h4>
                            <div class="row g-2">
                                <?php
                                $galleryImages = ['footer-1.jpg', 'footer-2.jpg', 'footer-3.jpg', 'footer-4.jpg', 'footer-5.jpg', 'footer-6.jpg'];
                                foreach ($galleryImages as $img):
                                    $galleryImage = file_exists("img/" . $img) ? htmlspecialchars($img) : 'default.jpg';
                                ?>
                                <div class="col-4">
                                    <div class="rounded overflow-hidden">
                                        <img src="img/<?php echo $galleryImage; ?>" class="img-zoomin img-fluid rounded w-100" alt="Gallery Image">
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Footer End -->

        <!-- Copyright Start -->
        <div class="container-fluid copyright bg-dark py-4">
            <div class="container">
                <div class="row">
                    <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                        <span class="text-light"><a href="index.php"><i class="fas fa-copyright text-light me-2"></i>Newsers</a>, All rights reserved.</span>
                    </div>
                    <div class="col-md-6 my-auto text-center text-md-end text-white">
                        Designed By <a class="border-bottom" href="https://htmlcodex.com">HTML Codex</a> Distributed By <a href="https://themewagon.com">ThemeWagon</a>
                    </div>
                </div>
            </div>
        </div>
        <!-- Copyright End -->

        <!-- Back to Top -->
        <a href="#" class="btn btn-primary border-2 border-white rounded-circle back-to-top"><i class="fa fa-arrow-up"></i></a>   

        <!-- JavaScript Libraries -->
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="lib/easing/easing.min.js"></script>
        <script src="lib/waypoints/waypoints.min.js"></script>
        <script src="lib/owlcarousel/owl.carousel.min.js"></script>

        <!-- Template Javascript -->
        <script src="js/main.js"></script>
        <script>
        $(document).ready(function() {
            $('.share-btn').click(function(e) {
                e.preventDefault();
                var newsId = $(this).data('id');
                $.post('share.php', { id: newsId }, function(data) {
                    if (data.success) {
                        var shareCount = parseInt($(`a[data-id="${newsId}"]`).text().match(/\d+/)[0]) + 1;
                        $(`a[data-id="${newsId}"]`).html(`<i class="fa fa-arrow-up"></i> ${shareCount} Share${shareCount == 1 ? '' : 's'}`);
                    }
                }, 'json');
            });
        });
        </script>
</body>
</html>
<?php
$conn->close();
?>