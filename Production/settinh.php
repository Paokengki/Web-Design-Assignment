<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Setting</title>

    <style>
        body{
            font-family: Arial;
            background:#f2f2f2;
        }

        .modal{
            display:flex;
            position:fixed;
            top:0;
            left:0;
            width:100%;
            height:100%;
            background:rgba(0,0,0,0.5);
            justify-content:center;
            align-items:center;
        }

        .modal-content{
            background:white;
            width:400px;
            padding:20px;
            border-radius:10px;
        }

        label{
            display:flex;
            justify-content:space-between;
            margin-top:15px;
        }

        button{
            margin-top:20px;
            width:100%;
            padding:10px;
            background:#EB984E;
            color:white;
            border:none;
            border-radius:6px;
            cursor:pointer;
        }

        button:hover{
            background:#ff6600;
        }
    </style>
</head>

<body>

<div id="modal" class="modal">
    <div class="modal-content">

        <h2>Settings</h2>

        <label>
            Dark Mode
            <input type="checkbox">
        </label>

        <label>
            Notifications
            <input type="checkbox">
        </label>

        <label>
            Language
            <select>
                <option>English</option>
                <option>Malay</option>
            </select>
        </label>

        <!-- 只做关闭功能 -->
        <button onclick="closeModal()">Save Settings</button>

    </div>
</div>

<script>
function closeModal(){
    document.getElementById("modal").style.display = "none";
}
</script>

</body>
</html>