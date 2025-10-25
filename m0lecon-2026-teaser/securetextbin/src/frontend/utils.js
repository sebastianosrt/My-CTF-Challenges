async function uploadFile(fileName, fileBuffer, fileId, BACKEND) {
    const fd = new FormData();
    const blob = new Blob([fileBuffer], { type: 'text/plain' });
    fd.append('file', blob, fileName);
    fd.append('id', fileId);
    return await fetch(BACKEND, { method: 'POST', body: fd });
}

async function uploadText(fileBuffer, fileId, BACKEND) {
    const params = new URLSearchParams();
    params.set('file', fileBuffer);
    params.set('id', fileId);
    return await fetch(BACKEND, { method: 'POST', body: params });
}

export { uploadFile, uploadText};