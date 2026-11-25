'use strict';

async function loadCart() {

    hideBanner();

    const res = await apiFetch('/api/cart');

    if (!res) return;

    if (!res.ok) {
        showBanner('Unable to load cart.');
        return;
    }

    const body = await res.json();

    const cartItems = document.getElementById('cartItems');

    cartItems.innerHTML = '';

    body.data.forEach(function(item) {

        const row = document.createElement('tr');

        row.innerHTML = `
            <td>${item.product_id}</td>
            <td>${item.qty}</td>
            <td>${Number(item.unit_price).toFixed(2)}</td>
            <td>${Number(item.subtotal).toFixed(2)}</td>
            <td>
            <button onclick="updateCart(${item.id}, ${item.qty})">
    Update
</button>
<button onclick="removeCart(${item.id})">
    Remove
</button>            </td>
        `;

        cartItems.appendChild(row);
    });

    document.getElementById('total').textContent =
        Number(body.total).toFixed(2);
}

document.addEventListener('DOMContentLoaded', loadCart);
async function addToCart(event) {

    event.preventDefault();

    hideBanner();

    const productId = Number(
        document.getElementById('productId').value
    );

    const quantity = Number(
        document.getElementById('quantity').value
    );

    const res = await apiFetch('/api/cart', {
        method: 'POST',

        headers: {
            'Content-Type': 'application/json'
        },

        body: JSON.stringify({
            product_id: productId,
            qty: quantity
        })
    });

    if (!res) return;

    if (!res.ok) {
        showBanner('Unable to add product.');
        return;
    }

    document.getElementById('cartForm').reset();

    loadCart();
}

document.getElementById('cartForm')
    .addEventListener('submit', addToCart);
    async function updateCart(id, oldQty) {

    const value = prompt("Enter new quantity:", oldQty);

    if (value === null) return;

    const quantity = Number(value);

    if (!Number.isInteger(quantity) || quantity < 1) {
        showBanner('Quantity must be at least 1.');
        return;
    }

    hideBanner();

    const res = await apiFetch('/api/cart/' + id, {
        method: 'PATCH',

        headers: {
            'Content-Type': 'application/json'
        },

        body: JSON.stringify({
            qty: quantity
        })
    });

    if (!res) return;

    if (!res.ok) {
        showBanner('Unable to update quantity.');
        return;
    }

    loadCart();
}
async function removeCart(id) {

    if (!confirm("Remove this product?")) {
        return;
    }

    hideBanner();

    const res = await apiFetch('/api/cart/' + id, {
        method: 'DELETE'
    });

    if (!res) return;

    if (!res.ok) {
        showBanner('Unable to remove product.');
        return;
    }

    loadCart();
}