<?php
	session_start();
	if ((!isset($_SESSION['loginid'])) || ($_SESSION['loginid'] == ""))
	{
		header('Location: ./index.php');
	}
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">        
        <link href="css/jquery-ui.min.css" rel="stylesheet">
        <link href="css/bootstrap.min.css" rel="stylesheet">
        <link href="css/bootstrap-responsive.css" rel="stylesheet">
    </head>
<body>
<?php
  	include "mssql-dbnew.php";

	$kdprog = $_SESSION['program'];
	$login = $_SESSION['loginid'];

	$tgl = date('Y-m-d 23:59:59');
	if ($kdprog == ''){$kdprog = '01';}

	$tsql = "	select 	a.*, b.m_add+b.m_edit+b.m_delete+b.m_print as coakses
				from 	msmenu a, msakses b 
				where 	a.m_program = '".$kdprog."' and 
						a.m_program = b.m_program and 
						a.m_kode = b.m_kode and 
						b.m_login = '".$login."' and 
						right(a.m_kode,5) = '00000' and 
						( b.m_akses = 'Y' ) 
				order by a.m_kode asc" ;
	
	//echo $tsql ."<br>";
	//$stmt = sqlsrv_query( $con_dbnew, $tsql);
	$stmt = $con_dbnew->query($tsql);

	?>
    <div class="navbar">
        <div class="navbar-inner">
            <!-- Menampilkan tombol trigger -->
            <a class="btn btn-navbar" data-toggle="collapse" data-target=".navbar-responsive-collapse">
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </a><!-- Akhir dari tombol triger -->
            <!-- Komponen navbar -->
            <div class="nav-collapse collapse navbar-responsive-collapse">
                <div group="btn-group">
		            <a class="brand" style="font-family:'Lucida Sans Unicode', 'Lucida Grande', sans-serif;font-weight:bold" ><img src="images/svashta black.png" height="40" width="200" /></a>
					<?php
                    $fheader = 'Y';
                    $topmenu = '' ;
                    while ($row = $stmt->fetch_assoc()) 
                    {
                        $class = '';
                        $kode = $row['m_kode'];
                        $akses = $row['coakses'];
                        $nama = $row['m_nama'];
                        $param = $kdprog.'/'.$kode.'/'.$nama.'/'.$akses;
                        
                        $link = 'menu-pos2.php?prm='.base64_encode($param);
                        if ($row['m_submenu'] == '1')
                        { $class = 'class="btn btn-default input-small'; }
                        else if ($row['m_submenu'] == '0')
                        { $class = 'class="btn btn-default input-small'; }
                        
                        echo '<a href="'.$link.'" '.$class.'" style="margin-top:10px;" >'.$nama.'</a>';
                    }
                    ?>
                    <img class="btn btn-mini" src="images/button-off.png" width="30" onClick="closeprog()" style="cursor:pointer"/>
                </div>
            </div><!-- /.nav-collapse -->
        </div><!-- /navbar-inner -->
        <div class="pull-right">
        </div>    
    </div><!-- /navbar -->                    

    <div id="dialog-tableharga">
        <span id="dataharga">
        </span>
    </div>
    
    <div id="dialog-tablehargalm">
        <span id="datahargalm">
        </span>
    </div>

	<script src="js/jquery-1.9.1.min.js"></script>
	<script src="js/jquery-ui.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script type="text/javascript">
		$(function() {
			$( "#dialog-tableharga" ).dialog({
				autoOpen: false,
				height:300,
				width:400,
				modal: true,
				title:'Today Gold Price' });

			$( "#dialog-tablehargalm" ).dialog({
				autoOpen: false,
				height:550,
				width:350,
				modal: true,
				title:'Harga LM' });
		});

		function opentgp()
		{
			var data={};

			var fungsi=function(respon){
					$("#dataharga").html(respon);
				};
			$.get('tabel-tgp2.php',data,fungsi);
			
			$( "#dialog-tableharga" ).dialog( "open" );
		}

		function openhargalm()
		{
			var data={};

			var fungsi=function(respon){
					$("#datahargalm").html(respon);
				};
			$.get('tabel-hargalm.php',data,fungsi);
			
			$( "#dialog-tablehargalm" ).dialog( "open" );
		}
		
		function closeprog()
		{
			window.open("logout.php",'_self');
		}
	</script>
</body>
</html>