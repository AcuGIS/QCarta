<?php
    session_start();
    require('incl/const.php');
    require('class/database.php');
    require('class/table.php');
    require('class/table_ext.php');	
    require('class/layer.php');
    require('class/qgs_layer.php');
    require('class/geostory.php');
    	
    if(!isset($_SESSION[SESS_USR_KEY])) {
        header('Location: ../login.php');
        exit(1);
    }

    // Initialize content array in session if it doesn't exist
    if (!isset($_SESSION['content'])) {
        $_SESSION['content'] = [];
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['id'] ?? '';
        $title = $_POST['title'] ?? '';
        $content = $_POST['content'] ?? '';
        
        if ($id) {
            $_SESSION['content'][$id] = [
                'type' => 'html',
                'title' => $title,
                'content' => $content
            ];
        }
        
        // Redirect back to index
        header('Location: geostory-editor.php');
        exit(0);
    }

    // Get content ID from URL
    $id = $_GET['id'] ?? '';
    $content = $_SESSION['content'][$id] ?? [
        'type' => 'html',
        'title' => 'New HTML Content',
        'content' => ''
    ];
?>
<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<title>QCarta</title>
<meta http-equiv="content-type" content="text/html; charset=utf-8">
<link rel="stylesheet" href="../assets/dist/css/quail.css" type="text/css" media="screen">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">	
	
	<?php include("incl/meta.php"); ?>
	<link href="assets/dist/css/side_menu.css" rel="stylesheet">
	<link href="assets/dist/css/table.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
    <style>
        body {
            padding: 20px;
            background-color: #f8f9fa;
        }
        .editor-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .btn-group {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
    </style>
</head>
<body>
    <div id="container" style="display:block">
        <?php const NAV_SEL = 'Geostories'; const TOP_PATH='../'; const ADMIN_PATH='';
					include("incl/navbar.php"); ?>
		<br class="clear">
		<?php include("incl/sidebar.php"); ?>
		
        <div class="editor-container">
            <h1 class="mb-4">HTML Content Editor</h1>
            
            <form method="POST" id="contentForm">
                <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">
                
                <div class="mb-3">
                    <label for="title" class="form-label">Title</label>
                    <input type="text" class="form-control" id="title" name="title" 
                        value="<?php echo htmlspecialchars($content['title']); ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="content" class="form-label">Content</label>
                    <textarea id="content" name="content"><?php echo htmlspecialchars($content['content']); ?></textarea>
                </div>
                
                <div class="btn-group">
                    <a href="geostory-editor.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Content</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#content').summernote({
                height: 400,
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'underline', 'clear']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link', 'picture']],
                    ['view', ['fullscreen', 'codeview', 'help']]
                ]
            });
        });
    </script>
</body>
</html>
