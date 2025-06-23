function update_progress_bar(fname, perc){
	$('.progress .progress-bar').html(fname + ' ' + perc + '%');
	$('.progress .progress-bar').css('width', perc+'%');
	$('.progress .progress-bar').prop('aria-valuenow', perc);
}

function update_progress_bytes(n){
	$('.progress .progress-bar').html(n + 'bytes');
	$('.progress .progress-bar').prop('aria-valuenow', n);
}


async function uploadFile(tag_id, upload_uri, fn_post, fn_data) {
  const fileInput = document.getElementById(tag_id);
	let upload_size = 0;
	$("div .progress").show();
					
	for (var i = 0; i < fileInput.files.length; i++) {
		const file = fileInput.files[i];
		const upload_name = file.name;
		
		update_progress_bar(file.name, 0);
		
		let chunkSize = post_max_size - 1000; // 16MB per chunk
		if(chunkSize >= file.size){
			chunkSize = file.size;
		}
		// Split the file into 10MB chunks
	  let start = 0;
	  while (start < file.size) {
	    const chunk = file.slice(start, start + chunkSize);
			await uploadChunk(upload_uri, upload_name, start, chunk);
			
			start += chunkSize;
			perc = Math.round((start / file.size) * 100.0);
			update_progress_bar(file.name, perc);
	  }
		upload_size += file.size;
	}
	
	fn_data.append('upload_size', upload_size);
	
	fn_post(fn_data);
}

async function uploadURL(tag_id, upload_uri, fn_post, fn_data) {

	const urlData = new FormData();
	urlData.set('action', 'url');
	urlData.set('url', fn_data.get('src_url'));

	try {
    const response = await fetch(upload_uri, {
      method: 'POST', body: urlData, dataType: "json",
    });

    if (!response.ok) {
      throw new Error('Network response was not ok');
    }
		
		let data = await response.json();
		let file_size = data.size;
		let file_name = data.name;

		$("div .progress").show();
		if(file_size == 0){	// chunked transfer
			update_progress_bar(file_name, 100);
			update_progress_bytes(0);
		}else{
			update_progress_bar(file_name, 0);
		}
		
		let upload_size = 0, prev_upload_size = 0;
	  while ((upload_size < file_size) || (file_size == 0)) {
			
			const statusData = new FormData();
			statusData.set('action', 'status');
			statusData.set('name', file_name);
			
			const response = await fetch(upload_uri, {
	      method: 'POST', body: statusData, dataType: "json",
	    });
			
			if (!response.ok) {
	      throw new Error('Status response was not ok');
	    }
			
			data = await response.json();
			upload_size = data.size;
			
			if(file_size == 0){	// chunked transfer
				update_progress_bytes(upload_size);
				if(!data.is_downloading){
					break;
				}
			}else{
				perc = Math.round((upload_size / file_size) * 100.0);
				update_progress_bar(file_name, perc);
			}
	  }
		
		fn_data.set('source[]', file_name);
		fn_data.set('upload_size', upload_size);
		fn_post(fn_data);
		
  } catch (error) {
    console.error('Error uploading by URL:', error);
  }	
}

async function uploadChunk(upload_uri, upload_name, start, chunk) {
  
  const formData = new FormData();
	formData.append('action', 'upload');
	formData.append('start', start);
	formData.append('source', upload_name);
	formData.append('chunk', chunk);

  try {
    const response = await fetch(upload_uri, {
      method: 'POST',
      body: formData,
			dataType: "json",
    });

    if (!response.ok) {
      throw new Error('Network response was not ok');
    }

    //const data = await response.json();
    //console.log('Chunk uploaded successfully:', data);
  } catch (error) {
    console.error('Error uploading chunk:', error);
  }
}