
<link rel="stylesheet" href="../css/updates.css">
    <link
      rel="icon"
      type="image/x-icon"
      href="/assets/img/S1ProjectsLogo.png"
    />
    <title>S1PROJECTS</title>
<div class="updatesWrapper">
<section class="updatesTimeline">
<?php 
  require_once '../dbcon.php';
  $sql = "SELECT * FROM `updates` ORDER BY `updates`.`id` DESC" ;
  $result = $conn -> query($sql); 
  if($result->num_rows >0) {
    $updates = $result->fetch_all(MYSQLI_ASSOC);
    foreach($updates as $update) {
    ?>
    <article class="updateCard">
      <h1 class="updateNumber">AKTUALIZACJA #<?php echo str_pad($update['id'], 4,'0', STR_PAD_LEFT)?></h1>
      <p class="updateDate"><?php echo date('d-m-Y', strtotime($update['date']))?></p>
      <div class="updateChanges">
        <h2>Główne zmiany:</h2>
        <ul>
          <?php 
            $changes = explode(", ",$update['changes']);
            foreach($changes as $change) {
              ?> <li><?php echo $change?></li> <?php
            }
          ?>
        </ul>
      </div>
    </article>

    <?php
    }
  }
  $conn->close();
?>  
</section>
</div>