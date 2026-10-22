readme* text
Step for run the code 
1: Rename the format called ASSFOOD
2: Put this folder inside to xampp\htdocs\ASSFOOD

Login part:
Customer (Alr registered)
Username: xiaoyan1
Password: Yylim12@

Admin (Alr registered)
Username: Wu Tong
Password: Admin12*

Payment part：
How to scan the qr code and pay the ewallet successful ?
Step 1: open cmd
Step 2: type cd C:\xampp\htdocs\ASSFOOD
Step 3: type cloudflared tunnel --url http://localhost
Step 4: paste the link to the base url there (order_qr_view.php line 42)

For using stripe payment (test account):
Card Number: 4242 4242 4242 4242
YY/MM: 12/34
CVV: 123
Card Holder Name: Lim Yu Yang
Country: Malaysia


