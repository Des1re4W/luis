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
        body { background: #fbfbfb; overflow-x: hidden; }
        .navbar { background: #051c3c !important; }
        .story-section { padding: 60px 0; }
        .icon-box {
            padding: 25px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            transition: 0.3s;
            height: 100%;
        }
        .icon-box:hover { transform: translateY(-5px); }
        .icon-box i { font-size: 2.5rem; color: #ffc107; }
        footer { background: #051c3c; color: white; }
        
        .carousel-overlay {
            min-height: 450px;
            height: 60vh;
            background-size: cover;
            background-position: center;
        }

        .carousel-item {
            transition: transform 0.8s ease-in-out; 
        }

        .carousel-indicators [data-bs-target] {
            background-color: #ffc107;
        }

        @media (max-width: 768px) {
            .carousel-overlay {
                height: 350px;
                min-height: unset;
            }
            .carousel-overlay h1 {
                font-size: 2rem !important;
            }
            .carousel-overlay p {
                font-size: 1rem !important;
            }
        }
    </style>
</head>
<body>

<!-- NAVIGATION -->
<nav class="navbar navbar-expand-lg navbar-dark sticky-top shadow">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="dashboard.php">
            <img src="../assets/logo.jpg" style="width:40px; height:40px; border-radius:50%; border:2px solid white;" alt="Logo">
            <strong>R. Taipan Hardware</strong>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link" href="dashboard.php?view=home">Home</a></li>
                <li class="nav-item"><a class="nav-link active" href="aboutus.php">About Us</a></li>
            </ul>
        </div>
    </div>
</nav>

<!-- HEADER SECTION (CAROUSEL) -->
<div id="aboutCarousel" class="carousel slide" data-bs-ride="carousel">
    <div class="carousel-indicators">
        <button type="button" data-bs-target="#aboutCarousel" data-bs-slide-to="0" class="active" aria-current="true" aria-label="Slide 1"></button>
        <button type="button" data-bs-target="#aboutCarousel" data-bs-slide-to="1" aria-label="Slide 2"></button>
        <button type="button" data-bs-target="#aboutCarousel" data-bs-slide-to="2" aria-label="Slide 3"></button>
    </div>

    <div class="carousel-inner">
        <div class="carousel-item active">
            <div class="carousel-overlay d-flex align-items-center justify-content-center" style="background-image: linear-gradient(rgba(5, 28, 60, 0.7), rgba(5, 28, 60, 0.7)), url('../assets/s5.jpg');">
                <div class="container text-center text-white px-3">
                    <h1 class="display-4 fw-bold">Our Story</h1>
                    <p class="lead fs-5">Building dreams with quality materials since our first branch.</p>
                </div>
            </div>
        </div>

        <div class="carousel-item">
            <div class="carousel-overlay d-flex align-items-center justify-content-center" style="background-image: linear-gradient(rgba(5, 28, 60, 0.7), rgba(5, 28, 60, 0.7)), url('../assets/s1.jpg');">
                <div class="container text-center text-white px-3">
                    <h1 class="display-4 fw-bold">Quality Supplies</h1>
                    <p class="lead fs-5">Providing the best roofing and construction materials in the industry.</p>
                </div>
            </div>
        </div>

        <div class="carousel-item">
            <div class="carousel-overlay d-flex align-items-center justify-content-center" style="background-image: linear-gradient(rgba(5, 28, 60, 0.7), rgba(5, 28, 60, 0.7)), url('../assets/s4.jpg');">
                <div class="container text-center text-white px-3">
                    <h1 class="display-4 fw-bold">Trusted Service</h1>
                    <p class="lead fs-5">Expanding our reach to serve more Filipino households.</p>
                </div>
            </div>
        </div>
    </div>

    <button class="carousel-control-prev" type="button" data-bs-target="#aboutCarousel" data-bs-slide="prev">
        <span class="carousel-control-prev-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Previous</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#aboutCarousel" data-bs-slide="next">
        <span class="carousel-control-next-icon" aria-hidden="true"></span>
        <span class="visually-hidden">Next</span>
    </button>
</div>

<!-- MAIN STORY -->
<section class="story-section">
    <div class="container">
        <div class="row align-items-center gy-4">
            <div class="col-lg-6 text-center">
                <img src="../assets/logo.jpg" class="img-fluid rounded shadow-lg" style="max-height: 400px; object-fit: cover;" alt="Hardware Store Logo">
            </div>
            <div class="col-lg-6">
                <h2 class="mb-4 fs-3 fs-md-2">How R. Taipan Construction Supply and Services Starts</h2>
                <p class="text-secondary">
                <strong>R. Taipan Construction Supply and Services, OPC</strong> was established in 2022 with a simple yet meaningful vision: to provide high-quality and affordable construction materials for every Filipino household and building project. Starting from its first branch located in Taytay, Rizal, the company steadily gained the trust and confidence of its valued customers through reliable products and dedicated service.
                </p>
                <p class="text-secondary">
                    As the years progressed, the business continuously expanded its range of services and product offerings, from basic hardware tools to large-scale construction materials such as steel roofing, cement, paint, and industrial chemicals. Every nail, can of paint, and bag of cement that leaves the store reflects the company’s strong commitment to quality, customer satisfaction, and excellence in service.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- VISION & MISSION -->
<section class="bg-light py-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="icon-box text-center text-md-start">
                    <div class="text-center"><i class="bi bi-eye"></i></div>
                    <h4 class="mt-3 text-center text-md-start">Our Vision</h4>
                    <p class="text-muted small">At R. Taipan Construction Supply and Services, our vision is to be the leading provider of innovative and sustainable steel roofing solutions in the industry. We aspire to set the standard for quality, reliability, and customer satisfaction, transforming the way people think about roofing.
                    We envision a future where every structure is topped with durable, aesthetically pleasing roofs that not only enhance the beauty of homes and businesses but also contribute to environmental sustainability. Our commitment to excellence drives us to continuously improve our products and services, empowering our clients to make informed choices that protect their investments and enrich their communities.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="icon-box text-center text-md-start">
                    <div class="text-center"><i class="bi bi-rocket-takeoff"></i></div>
                    <h4 class="mt-3 text-center text-md-start">Our Mission</h4>
                    <p class="text-muted small">At R. Taipan Construction Supply and Services, our mission is to provide top-quality steel roofing solutions that ensure durability, safety, and aesthetic appeal for every client. We are dedicated to delivering exceptional service and expert craftsmanship, fostering strong relationships built on trust and integrity.
                    We strive to understand the unique needs of our customers, offering tailored solutions that enhance their properties while adhering to the highest industry standards. Our commitment to innovation and sustainability drives us to utilize advanced materials and techniques, ensuring that our roofs stand the test of time.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-12">
                <div class="icon-box text-center text-md-start">
                    <div class="text-center"><i class="bi bi-heart"></i></div>
                    <h4 class="mt-3 text-center text-md-start">Our Values</h4>
                    <p class="text-muted small">We are committed to providing excellent customer satisfaction through reliable service, 
                        quality products, and a convenient shopping experience. We uphold integrity by conducting every transaction with honesty, transparency, and professionalism. Through innovation and continuous improvement, we aim to deliver efficient and modern solutions that meet the needs of our customers. We value teamwork, accountability, and excellence in all aspects of our operations to ensure trust, reliability, and long-term success. Our goal is to make construction supplies and services accessible, affordable, and dependable for every customer.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer class="py-4 mt-5 text-center">
    <div class="container">
        <p class="mb-0">© 2026 R. Taipan Hardware Store. All Rights Reserved.</p>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>