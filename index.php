<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sari-Sari Store POS</title>
<script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
<style>
*{box-sizing:border-box;font-family:Arial,sans-serif}
body{margin:0;background:#f1f5f2;color:#333}
header{background:#176b45;color:white;padding:20px;text-align:center}
.container{display:grid;grid-template-columns:2fr 1fr;gap:20px;padding:20px}
.card{background:white;padding:20px;border-radius:10px;box-shadow:0 2px 8px #0001}
h2{color:#176b45}
.products{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px}
.product{padding:15px;border:1px solid #ddd;border-radius:8px;text-align:center}
.product svg{max-width:100%;height:55px}
button{background:#176b45;color:white;border:0;padding:10px;border-radius:5px;cursor:pointer;margin:3px}
button:hover{background:#0e5032}button:disabled{background:#aaa;cursor:not-allowed}
input{width:100%;padding:10px;margin:6px 0;border:1px solid #ccc;border-radius:5px}
.cart-item{padding:10px;border-bottom:1px solid #ddd}
.total{font-size:22px;font-weight:bold;color:#176b45}
table{width:100%;border-collapse:collapse}td,th{padding:10px;border-bottom:1px solid #ddd;text-align:left}
.barcode{width:150px;height:50px}
.scan-box{background:#e8f5ec;padding:15px;border-radius:8px;margin-bottom:15px}
.scan-box label{font-weight:bold;color:#176b45}
.status{padding:10px;margin:12px 20px;border-radius:6px;background:#fff4d6}
@media(max-width:750px){.container{grid-template-columns:1fr}}
</style>
</head>
<body>
<header><h1>🛒 SARI-SARI STORE POS</h1><p>Point of Sale and Inventory System</p></header>
<div id="status" class="status">Connecting to Supabase...</div>
<div class="container">
<div class="card">
<h2>Available Products</h2>
<div class="scan-box">
<label>Scan Product Barcode</label>
<input type="text" id="barcodeInput" placeholder="Scan or enter barcode here..." onkeydown="if(event.key==='Enter')scanBarcode()">
<button onclick="scanBarcode()">Scan Product</button>
</div>
<input type="text" id="search" placeholder="Search product..." oninput="displayProducts()">
<div class="products" id="products"></div>
</div>
<div class="card">
<h2>Customer Order</h2>
<input type="text" id="customer" placeholder="Customer Name">
<div id="cart"><p>No products selected.</p></div>
<hr>
<p>Subtotal:</p><h2 id="total">₱0.00</h2>
<label>Cash Received:</label>
<input type="number" id="cash" placeholder="Enter amount" min="0" oninput="calculateChange()">
<p>Change: <strong id="change">₱0.00</strong></p>
<button onclick="checkout()">Complete Sale</button>
<button onclick="clearCart()">Clear Cart</button>
</div>
</div>
<div class="card" style="margin:20px">
<h2>Inventory Management</h2>
<input type="text" id="newName" placeholder="Product Name">
<input type="number" id="newPrice" placeholder="Product Price" min="0" step="0.01">
<input type="number" id="newStock" placeholder="Stock Quantity" min="0" step="1">
<input type="text" id="newBarcode" placeholder="Barcode Number (optional)">
<button onclick="addProduct()">Add Product</button>
<h3>Product List</h3>
<div style="overflow-x:auto"><table>
<thead><tr><th>Product</th><th>Barcode Number</th><th>Barcode</th><th>Price</th><th>Stock</th><th>Action</th></tr></thead>
<tbody id="inventory"></tbody>
</table></div>
</div>

<script>
// 1. Replace these two values with your Supabase project's URL and anon/public key.
// Never put your service_role key in browser code.
const SUPABASE_URL = "https://YOUR_PROJECT_ID.supabase.co";
const SUPABASE_ANON_KEY = "YOUR_SUPABASE_ANON_KEY";
const db = window.supabase.createClient(SUPABASE_URL, SUPABASE_ANON_KEY);

let products = [];
let cart = [];

function setStatus(message, error=false){
 const el=document.getElementById("status");
 el.textContent=message;
 el.style.background=error?"#ffe1e1":"#e8f5ec";
}
function money(amount){return "₱"+Number(amount).toFixed(2)}
function escapeHTML(value){return String(value).replace(/[&<>"']/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;"}[c]))}
function generateBarcode(){
 let code;
 do{code=String(Date.now())+String(Math.floor(Math.random()*1000)).padStart(3,"0")}
 while(products.some(p=>p.barcode===code));
 return code;
}
function createBarcode(elementId,barcode){
 if(typeof JsBarcode==="undefined")return;
 try{JsBarcode("#"+elementId,String(barcode),{format:"CODE128",width:1.5,height:40,displayValue:true,fontSize:10,margin:3})}
 catch(e){console.error("Barcode error:",e)}
}

// 2. Read products from the Supabase products table.
async function loadProducts(){
 setStatus("Loading products from Supabase...");
 const {data,error}=await db.from("products").select("*").order("product_id");
 if(error){setStatus("Supabase error: "+error.message,true);return}
 products=(data||[]).map(p=>({
   id:p.product_id,name:p.product_name,price:Number(p.price),
   stock:p.stock,barcode:p.barcode
 }));
 displayProducts();displayInventory();displayCart();
 setStatus("Connected to Supabase. Inventory is up to date.");
}

function displayProducts(){
 const search=document.getElementById("search").value.toLowerCase();
 const filtered=products.filter(p=>p.name.toLowerCase().includes(search));
 document.getElementById("products").innerHTML=filtered.map(p=>`
 <div class="product">
 <h3>${escapeHTML(p.name)}</h3><p>${money(p.price)}</p><p>Stock: ${p.stock}</p>
 <svg id="product-barcode-${p.id}"></svg>
 <button ${p.stock<=0?"disabled":""} onclick="addToCart(${p.id})">Add to Cart</button>
 </div>`).join("");
 filtered.forEach(p=>createBarcode("product-barcode-"+p.id,p.barcode));
}
function displayInventory(){
 document.getElementById("inventory").innerHTML=products.map(p=>`
 <tr><td>${escapeHTML(p.name)}</td><td>${escapeHTML(p.barcode)}</td>
 <td><svg id="inventory-barcode-${p.id}" class="barcode"></svg></td>
 <td>${money(p.price)}</td><td>${p.stock}</td>
 <td><button onclick="printBarcode(${p.id})">Print Barcode</button>
 <button onclick="deleteProduct(${p.id})">Delete</button></td></tr>`).join("");
 products.forEach(p=>createBarcode("inventory-barcode-"+p.id,p.barcode));
}
function scanBarcode(){
 const code=document.getElementById("barcodeInput").value.trim();
 if(!code)return;
 const product=products.find(p=>String(p.barcode)===code);
 if(product)addToCart(product.id);else alert("Product barcode not found!");
 document.getElementById("barcodeInput").value="";
 document.getElementById("barcodeInput").focus();
}
function addToCart(id){
 const product=products.find(p=>p.id===id);
 if(!product||product.stock<=0)return;
 const item=cart.find(p=>p.id===id);
 if(item){if(item.quantity>=product.stock){alert("Insufficient stock!");return}item.quantity++}
 else cart.push({...product,quantity:1});
 displayCart();
}
function displayCart(){
 let html="";
 cart.forEach(item=>{html+=`<div class="cart-item"><strong>${escapeHTML(item.name)}</strong><br>${money(item.price)} x ${item.quantity}<br>
 <button onclick="changeQuantity(${item.id},-1)">-</button><button onclick="changeQuantity(${item.id},1)">+</button></div>`});
 document.getElementById("cart").innerHTML=html||"<p>No products selected.</p>";
 const total=cart.reduce((s,i)=>s+i.price*i.quantity,0);
 document.getElementById("total").innerText=money(total);calculateChange();
}
function changeQuantity(id,amount){
 const item=cart.find(p=>p.id===id),product=products.find(p=>p.id===id);
 if(!item)return;
 if(amount>0&&item.quantity>=product.stock){alert("Insufficient stock!");return}
 item.quantity+=amount;if(item.quantity<=0)cart=cart.filter(p=>p.id!==id);
 displayCart();
}
function calculateChange(){
 const total=cart.reduce((s,i)=>s+i.price*i.quantity,0);
 const cash=Number(document.getElementById("cash").value)||0;
 document.getElementById("change").innerText=money(Math.max(0,cash-total));
}

// 3. Checkout calls a PostgreSQL function. The function records the sale and
// reduces stock in one database transaction to avoid partial updates.
async function checkout(){
 if(!cart.length){alert("Please select a product!");return}
 const total=cart.reduce((s,i)=>s+i.price*i.quantity,0);
 const cash=Number(document.getElementById("cash").value);
 if(!Number.isFinite(cash)||cash<total){alert("Insufficient payment!");return}
 const saleItems=cart.map(i=>({product_id:i.id,quantity:i.quantity,unit_price:i.price}));
 const {data,error}=await db.rpc("complete_sale",{
   p_customer_name:document.getElementById("customer").value.trim()||"Walk-in",
   p_cash_received:cash,
   p_items:saleItems
 });
 if(error){alert("Sale was not saved: "+error.message);return}
 const change=cash-total;
 let receipt="SARI-SARI STORE\n--------------------------\n";
 receipt+="Customer: "+(document.getElementById("customer").value.trim()||"Walk-in")+"\n";
 receipt+="Date: "+new Date().toLocaleString()+"\n\n";
 cart.forEach(i=>receipt+=`${i.name} x ${i.quantity} = ${money(i.price*i.quantity)}\n`);
 receipt+="\nTOTAL: "+money(total)+"\nCASH: "+money(cash)+"\nCHANGE: "+money(change)+"\n\nThank you! Come again!";
 alert(receipt);
 cart=[];document.getElementById("cash").value="";document.getElementById("customer").value="";
 await loadProducts();
}
function clearCart(){cart=[];document.getElementById("cash").value="";displayCart()}

// 4. Add a product directly to Supabase.
async function addProduct(){
 const name=document.getElementById("newName").value.trim();
 const price=Number(document.getElementById("newPrice").value);
 const stock=Number(document.getElementById("newStock").value);
 const barcode=document.getElementById("newBarcode").value.trim()||generateBarcode();
 if(!name||!Number.isFinite(price)||price<0||!Number.isInteger(stock)||stock<0){alert("Please enter valid product details!");return}
 if(products.some(p=>String(p.barcode)===barcode)){alert("This barcode is already assigned to another product!");return}
 const {error}=await db.from("products").insert({product_name:name,price,stock,barcode});
 if(error){alert("Could not add product: "+error.message);return}
 ["newName","newPrice","newStock","newBarcode"].forEach(id=>document.getElementById(id).value="");
 await loadProducts();
}
async function deleteProduct(id){
 if(cart.some(i=>i.id===id)){alert("Remove this item from the cart first.");return}
 if(!confirm("Delete this product?"))return;
 const {error}=await db.from("products").delete().eq("product_id",id);
 if(error){alert("Could not delete product: "+error.message);return}
 await loadProducts();
}
function printBarcode(id){
 const p=products.find(x=>x.id===id);if(!p)return;
 const svg=document.createElementNS("http://www.w3.org/2000/svg","svg");
 JsBarcode(svg,String(p.barcode),{format:"CODE128",width:2,height:65,displayValue:true,fontSize:14});
 const image=new XMLSerializer().serializeToString(svg);
 const w=window.open("","_blank","width=400,height=300");
 if(!w){alert("Please allow pop-ups to print barcode labels.");return}
 w.document.write(`<!DOCTYPE html><html><head><title>Product Barcode</title></head>
 <body style="font-family:Arial;text-align:center;padding:20px"><h3>${escapeHTML(p.name)}</h3>${image}
 <h3>${money(p.price)}</h3><script>window.onload=function(){window.print()}<\/script></body></html>`);
 w.document.close();
}
loadProducts();
</script>
</body>
</html>
