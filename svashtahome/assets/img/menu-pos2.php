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
	
	$parm = base64_decode($_GET['prm']);
	$dumb = explode('/',$parm);

	$kdprog = $dumb[0];
	$menuid = $dumb[1];
	$nm = $dumb[2];
	$akses = $dumb[3];

	$menuid = substr($menuid,0,1).'00000';
	$login = $_SESSION['loginid'];

	$tgl = date('Y-m-d 23:59:59');
	$mid = substr($menuid,0,1);

	$tsql = "	select 	a.*, b.m_add+b.m_edit+b.m_delete+b.m_print as coakses
				from 	msmenu a, msakses b 
				where 	a.m_program = '".$kdprog."' and 
						a.m_program = b.m_program and 
						a.m_kode = b.m_kode and 
						b.m_login = '".$login."' and 
						left(a.m_kode,1) = '".$mid."' and 
						a.m_kode <> '".$menuid."' and 
						right(a.m_kode,4) = '0000' and 
						( b.m_akses = 'Y' ) and a.m_status = '1'
				order by a.m_urutan asc" ;
	//echo $tsql;
	// $stmt = sqlsrv_query( $con_dbnew, $tsql);
	$stmt = $con_dbnew->query($tsql);
	?>
    
    <div class="navbar">
        <div class="navbar-inner">
	        <div class="btn-group-vertical">
                <div class="btn-group">
                <a class="brand" style="font-family:'Lucida Sans Unicode', 'Lucida Grande', sans-serif;font-weight:bold;cursor:pointer"  onClick="backmenu()"><img src="images/svashta black.png" height="40" width="200" /></a>
                </div>
                
				<?php
                $fheader = 'Y';
                $topmenu = '' ;
                while ($row = $stmt->fetch_assoc()) 
                {
                    $kode = $row['m_kode'];
                    $akses = $row['coakses'];
                    $nama = $row['m_nama'];
                    $param = $kdprog.'/'.$kode.'/'.$nama.'/'.$akses;
                    
                    if ($row['m_status'] == '1')
                        { $link = $row['m_object'].'?prm='.base64_encode($param); }
                    else if ($row['m_submenu'] == '1')
                        { $link = 'menu3.php?prm='.base64_encode($param); }
                    
                    if ($row['m_submenu'] == '0')
                    { 
                        $class = 'class="btn btn-default input-medium'; 
                        echo '<a href="'.$link.'" ><button style="margin-top:10px;" '.$class.'" type="button">'.$nama.'</button></a>';
                    }
                    ?>
		            <div class="btn-group">
					<?php 
						if($row['m_submenu'] == '1')
						{ 
							$class = 'class="btn btn-default input-medium';
							$dumb = explode('/',$param);
							$kdprog = $dumb[0];
							$menuid = $dumb[1];
							$menuid = substr($menuid,0,2).'0000';
							$mid = substr($menuid,0,2);
							$tsqlul = "	select 	a.*, b.m_add+b.m_edit+b.m_delete+b.m_print as coakses
										from 	msmenu a, msakses b 
										where 	a.m_program = '".$kdprog."' and 
												a.m_program = b.m_program and 
												a.m_kode = b.m_kode and 
												b.m_login = '".$login."' and 
												left(a.m_kode,2) = '".$mid."' and 
												a.m_kode <> '".$menuid."' and 
												( b.m_akses = 'Y' )
										order by a.m_kode asc" ;
							//$stmtul = sqlsrv_query( $con_dbnew, $tsqlul);
							$stmtul = $con_dbnew->query($tsqlul);
							echo '<a data-toggle="dropdown">
								  <button type="button" '.$class.'">'.$nama.'&nbsp;&nbsp;<span class="caret"></span></button></a>'; ?>
							<ul class="dropdown-menu">
							<?php
							//while( $rowul = sqlsrv_fetch_array( $stmtul, SQLSRV_FETCH_ASSOC))
							while ($rowul = $stmtul->fetch_assoc()) 
							{
								$kode  = $rowul['m_kode'];
								$akses = $rowul['coakses'];
								$nama  = $rowul['m_nama'];
								$param = $kdprog.'/'.$kode.'/'.$nama.'/'.$akses;
								$link2  = $rowul['m_object'].'?prm='.base64_encode($param);
								?>
				                <li><a href="<?php echo $link2 ;?>"><?php echo $rowul['m_nama']; ?></a></li>
								<?php
                            }
                            ?>
			                </ul>
						<?php
                        }
                    ?>
                    </div>
				<?php
				}
				?>
                <div class="btn-group">
                    <img class="btn btn-mini" src="images/button-off.png" width="30" onClick="closeprog()" style="cursor:pointer"/>
                </div>    
			</div>
        </div>
       
    </div>         

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

		function backmenu()
		{
			window.open("menu-pos1.php",'_self');
		}
	</script>
</body>
</html>		
