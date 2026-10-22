<?php
session_start();
include 'base.php';

$keyword = "";

if(isset($_GET['keyword'])){
    $keyword = mysqli_real_escape_string($connect,$_GET['keyword']);
}

$query = "SELECT * FROM products 
          WHERE name LIKE '%$keyword%' 
          AND is_available = 1";

$result = mysqli_query($connect,$query);
?>

<!DOCTYPE html>
<html>
<head>
<title>Search Result</title>
<link rel="stylesheet" href="css/index.css">
</head>

<body>

<div class="container">

<h2>Search Result for "<?php echo htmlspecialchars($keyword); ?>"</h2>

<div class="products-grid">

<?php
if(mysqli_num_rows($result)>0){

while($row=mysqli_fetch_assoc($result)){

$productid=$row['productid'];
$name=htmlspecialchars($row['name']);
$price=number_format($row['price'],2);
$image=$row['image'];
?>

<div class="product-card">

<div class="product-image">

<?php if($image!=""){ ?>

<img src="uploads/<?php echo $image; ?>" width="200">

<?php } ?>

</div>

<div class="product-info">

<h3><?php echo $name; ?></h3>

<p>RM <?php echo $price; ?></p>

<a href="product.php?id=<?php echo $productid; ?>" class="btn btn-primary">
View
</a>

</div>

</div>

<?php
}

}else{

echo "<p>No food found.</p>";

}
?>

</div>

<br>

<a href="index.php">⬅ Back to Home</a>

</div>

</body>
</html>