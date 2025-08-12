
// PayPal subscription button initialization
document.addEventListener('DOMContentLoaded', function(){
    const btn = document.getElementById('paypal-button-container');
    if (!btn) return;
    if (typeof paypal === 'undefined') return;
    paypal.Buttons({
        style: { layout: 'vertical', shape: 'rect' },
        createSubscription: function(data, actions) {
            const planId = btn.dataset.planId;
            return actions.subscription.create({ plan_id: planId });
        },
        onApprove: function(data, actions) {
            // TODO: call backend to mark subscription active
            fetch('/api/paypal-activate.php', {
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body: JSON.stringify({ subscriptionID: data.subscriptionID })
            }).then(r=>r.json()).then(j=>{
                if (j.success) location.href='/subscription/success.php';
                else alert(j.message||'Subscription activation failed');
            });
        }
    }).render('#paypal-button-container');
});
