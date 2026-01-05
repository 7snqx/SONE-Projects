<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lekcje</title>
    <script defer src="script.js"></script>
    <link rel="stylesheet" href="style.css">
    <link rel="icon" href="/assets/img/calendar.png">
</head>
<body>
    <header>
            <h1>PLAN LEKCJI</h1>
    </header>

    <?php
        include '../../dbcon.php';

        $querry = "SELECT * FROM `lessons`";
        $result = $conn->query($querry);
        if ($result->num_rows > 0) {
            $lessons = $result->fetch_all(MYSQLI_ASSOC);
            
            ?><script>const lessons = <?php echo json_encode($lessons, JSON_HEX_TAG); ?>;</script><?php

        }          
     ?>
    <main>
        <div class="lessons">
            <div id="currentLesson" class="Lesson">
                <!-- <div>
                    <h2>angielski, sala B4</h2>
                    <p class="time">8:00 - 8:45</p>
                </div>
                <p id="remaningTime">40 </p><label class="min">min</label> -->
            </div>
            <h2>Następnie</h2>
            <div id="nextLesson" class="Lesson"></div>
        </div>
        <?php 
            $querryToday = "SELECT * FROM `lessons` WHERE `day_of_week` = DATE_FORMAT(CURRENT_DATE, '%W') AND subject IS NOT NULL;";
            $resultToday = $conn->query($querryToday);
            if($resultToday->num_rows > 0) {
                ?> <table> 
                        <tr>
                            <th>Lekcja</th>
                            <th>Początek</th>
                            <th>Koniec</th>
                            <th>Przedmiot</th>
                            <th>Sala</th>
                        </tr>    
                <?php
                foreach($resultToday as $row) {
                    ?> 
                        <tr>
                        <td><hr><?php echo $row['lesson_number'] ?></td>
                        <td><?php echo substr($row['start_time'],0, -3) ?></td>
                        <td><?php echo substr($row['end_time'],0, -3) ?></td>
                        <td><?php echo $row['subject'] ?></td>
                        <td><?php echo $row['classroom'] ?></td>
                        </tr>
                    <?php
                }
                ?> </table> <?php
            }
        ?>
    </main>
</body>
</html>