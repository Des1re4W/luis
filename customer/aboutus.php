<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - R. Taipan Hardware Store</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background: #fbfbfb; }
        .navbar { background: #051c3c !important; }
        .about-header {
            background: linear-gradient(rgba(5, 28, 60, 0.8), rgba(5, 28, 60, 0.8)), url('br.jpg');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 100px 0;
            text-align: center;
        }
        .story-section { padding: 60px 0; }
        .icon-box {
            padding: 30px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: 0.3s;
            height: 100%;
        }
        .icon-box:hover { transform: translateY(-10px); }
        .icon-box i { font-size: 3rem; color: #ffc107; }
        footer { background: #051c3c; color: white; }
        .carousel-overlay {
    height: 500px;
    background-size: cover;
    background-position: center;
}

    .carousel-item {
    transition: transform 0.8s ease-in-out; 
}

.carousel-indicators [data-bs-target] {
    background-color: #ffc107;
}
    </style>
</head>
<body>

<!-- NAVIGATION -->
<nav class="navbar navbar-expand-lg navbar-dark sticky-top shadow">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="dashboard.php">
            <img src="../assets/logo.jpg" style="width:40px; height:40px; border-radius:50%; border:2px solid white;">
            <strong>R. Taipan Hardware Store</strong>
        </a>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="dashboard.php?view=home">Home</a></li>
                <li class="nav-item"><a class="nav-link active" href="aboutus.php">About Us</a></li>
            </ul>
        </div>
    </div>
</nav>

<!-- HEADER SECTION -->
<div id="aboutCarousel" class="carousel slide" data-bs-ride="carousel">
    <div class="carousel-indicators">
        <button type="button" data-bs-target="#aboutCarousel" data-bs-slide-to="0" class="active"></button>
        <button type="button" data-bs-target="#aboutCarousel" data-bs-slide-to="1"></button>
        <button type="button" data-bs-target="#aboutCarousel" data-bs-slide-to="2"></button>
    </div>

    <div class="carousel-inner">
        <div class="carousel-item active">
            <div class="carousel-overlay" style="background-image: linear-gradient(rgba(5, 28, 60, 0.7), rgba(5, 28, 60, 0.7)), url('../assets/s5.jpg');">
                <div class="container text-center text-white d-flex flex-column justify-content-center h-100">
                    <h1 class="display-3 fw-bold">Our Story</h1>
                    <p class="lead">Building dreams with quality materials since our first branch.</p>
                </div>
            </div>
        </div>

        <div class="carousel-item">
            <div class="carousel-overlay" style="background-image: linear-gradient(rgba(5, 28, 60, 0.7), rgba(5, 28, 60, 0.7)), url('../assets/s1.jpg');">
                <div class="container text-center text-white d-flex flex-column justify-content-center h-100">
                    <h1 class="display-3 fw-bold">Quality Supplies</h1>
                    <p class="lead">Providing the best roofing and construction materials in the industry.</p>
                </div>
            </div>
        </div>

        <div class="carousel-item">
            <div class="carousel-overlay" style="background-image: linear-gradient(rgba(5, 28, 60, 0.7), rgba(5, 28, 60, 0.7)), url('../assets/s4.jpg');">
                <div class="container text-center text-white d-flex flex-column justify-content-center h-100">
                    <h1 class="display-3 fw-bold">Trusted Service</h1>
                    <p class="lead">Expanding our reach to serve more Filipino households.</p>
                </div>
            </div>
        </div>
    </div>

    <button class="carousel-control-prev" type="button" data-bs-target="#aboutCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon"></span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#aboutCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon"></span>
    </button>
</div>

<!-- MAIN STORY -->
<section class="story-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-6 mb-4">
                <img src="../assets/logo.jpg" class="img-fluid rounded shadow-lg" alt="Hardware Store">
            </div>
            <div class="col-md-6">
                <h2 class="mb-4">How R. Taipan Construction Supply and Services Starts</h2>
                <p class="text-secondary">
                <strong>R. Taipan Construction Supply and Services, OPC</strong> was established in 2022 with a simple yet meaningful vision: to provide high-quality and affordable construction materials for every Filipino household and building project. Starting from its first branch located in Taytay, Rizal, the company steadily gained the trust and confidence of its valued customers through reliable products and dedicated service.
                </p>
                <p class="text-secondary">
                    As the years progressed, the business continuously expanded its range of services and product offerings. from basic hardware tools to large-scale construction materials such as steel roofing, cement, paint, and industrial chemicals. Every nail, can of paint, and bag of cement that leaves the store reflects the company’s strong commitment to quality, customer satisfaction, and excellence in service.
                </p>
                <div class="row mt-4">

                </div>
            </div>
        </div>
    </div>
</section>

<!-- VISION & MISSION -->
<section class="bg-light py-5">
    <div class="container text-center">
        <div class="row g-4">
            <div class="col-md-4">
                <div class="icon-box">
                    <i class="bi bi-eye"></i>
                    <h4 class="mt-3">Our Vision</h4>
                    <p class="text-muted small">At R. Taipan Construction Supply and Services, our vision is to be the leading provider of innovative and sustainable steel roofing solutions in the industry. We aspire to set the standard for quality, reliability, and customer satisfaction, transforming the way people think about roofing.
We envision a future where every structure is topped with durable, aesthetically pleasing roofs that not only enhance the beauty of homes and businesses but also contribute to environmental sustainability. Our commitment to excellence drives us to continuously improve our products and services, empowering our clients to make informed choices that protect their investments and enrich their communities.
Through collaboration, integrity, and a passion for craftsmanship, we aim to build lasting relationships with our customers and partners, fostering a culture of trust and shared success. Together, we will shape the landscape of roofing for generations to come.
</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="icon-box">
                    <i class="bi bi-rocket-takeoff"></i>
                    <h4 class="mt-3">Our Mission</h4>
                    <p class="text-muted small">At R. Taipan Construction Supply and Services, our mission is to provide top-quality steel roofing solutions that ensure durability, safety, and aesthetic appeal for every client. We are dedicated to delivering exceptional service and expert craftsmanship, fostering strong relationships built on trust and integrity.
We strive to understand the unique needs of our customers, offering tailored solutions that enhance their properties while adhering to the highest industry standards. Our commitment to innovation and sustainability drives us to utilize advanced materials and techniques, ensuring that our roofs stand the test of time.
Through continuous improvement and a focus on customer satisfaction, we aim to be the preferred choice for steel roofing in our community and beyond, contributing to the success and well-being of our clients and the environment.
</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="icon-box">
                    <i class="bi bi-heart"></i>
                    <h4 class="mt-3">Our Values</h4>
                    <p class="text-muted small">We are committed to providing excellent customer satisfaction through reliable service, 
                        quality products, and a convenient shopping experience. We uphold integrity by conducting every transaction with honesty, transparency, and professionalism. Through innovation and continuous improvement, we aim to deliver efficient and modern solutions that meet the needs of our customers. We value teamwork, accountability, and excellence in all aspects of our operations to ensure trust, reliability, and long-term success. Our goal is to make construction supplies and services accessible, affordable, and dependable for every customer.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- FOOTER (Consistent sa dashboard)[cite: 5] -->
<footer class="py-4 mt-5 text-center">
    <div class="container">
        <p class="mb-0">© 2026 R. Taipan Hardware Store. All Rights Reserved.</p>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>