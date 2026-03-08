<?php
session_start();

if(isset($_SESSION["user"])){
    header("Location: /index.html");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>Reports</title>
    </head>

    <body>
        <h1> Reporting Dashboard </h1>
        <p> Welcome, <?php echo htmlspecialchars($_SESSION["user"]["displayName"] ?? $_SESSION["user"]["email"]); ?></p>

        <button id="logout">Logout</button>

        <script>
            document.getElementById("logout").onclick = async () =>{
                await fetch("/api/logout", {
                    method: "POST"
                });
                window.location.href = "/";
            };
        </script>
    </body>
</html>