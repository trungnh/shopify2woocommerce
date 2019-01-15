# shopify2woocommerce

### PHP settings
```
max_execution_time = 0
max_input_time = -1
memory_limit = 128M # increase to 512 if error
max_input_vars = 3000
```

### Do import
```
- Đẩy data json vào file txt, mỗi file 1000 products
- Zip các file data => 1 hoặc nhiều file zip
- Upload file lên folder webroot/wp-content/uploads/woo_import/uploads/zip/source/
- Chạy API domain.com/wp-json/woo-import/v1/import_from_zip, method POST
```