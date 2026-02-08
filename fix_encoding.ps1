 = 'index.php'
 = Get-Content -Raw -Encoding UTF8 
 =  -replace 'Mant‚n la intranet con revisiones peri¢dicas, monitoreo, respaldos y soporte  gil sin sorpresas\.', 'Mantén la intranet con revisiones periódicas, monitoreo, respaldos y soporte ágil sin sorpresas.'
 =  -replace '49 \?/mes', '49 €/mes'
 =  -replace 'Brindar continuidad operativa con ajustes menores y soporte claro a 49 \?/mes\.', 'Brindar continuidad operativa con ajustes menores y soporte claro a 49 €/mes.'
Set-Content -Encoding UTF8  
