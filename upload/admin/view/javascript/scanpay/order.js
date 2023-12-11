

const urlParams = new URLSearchParams(window.location.search);
const token = urlParams.get('user_token');
const orderid = urlParams.get('order_id');
let rev = 0;

function buildTable(o) {
    const target = document.getElementById('scanpay--order--tbody');
    const tbody = target.cloneNode(false);
    const data = [
        ['Authorized', o.authorized],
        ['Captured', o.captured],
        ['Refunded', o.refunded],
        ['Voided', o.voided],
        ['Net payment', o.captured],
    ];
    for (const x of data) {
        const row = tbody.insertRow();
        const cell1 = row.insertCell();
        cell1.textContent = x[0];
        const cell2 = row.insertCell();
        cell2.textContent = x[1];
    }
    target.replaceWith(tbody);
}

function init() {
    document.querySelector('h1').innerHTML = '#' + orderid;
    const table = document.querySelectorAll('.table')[2];
    const row = table.insertRow();
    row.innerHTML = `<td>Net payment</td>
        <td></td>
        <td>
            <i class="fa fa-spinner fa-spin" aria-hidden="true"></i>
        </td>`;

    document.addEventListener("visibilitychange", () => {
        if (document.visibilityState !== "visible") return;

        // Add spinner to panel
        fetch('index.php?route=extension/payment/scanpay/ajaxScanpayOrder&user_token=' + token + '&orderid=' + orderid)
            .then((res) => res.json()).then((o) => {
                if (o.rev > rev) buildTable(o);
                console.log(o);
            });
    });
}

//             <button class="btn btn-success btn-xs"><i class="fa fa-plus-circle"></i></button>


/*
    if (isset($data['trnid'])) {

        $data['user_token'] = $this->session->data['user_token'];
        $data['currency'] = explode(' ', $data['authorized'])[1];
        $authorized = explode(' ', $data['authorized'])[0];
        $captured = explode(' ', $data['captured'])[0];
        $refunded = explode(' ', $data['refunded'])[0];
        $net = scanpay_submoney($captured, $refunded);
        $data['net_payment'] = $net . ' ' . $data['currency'];
        $data['net_payment_pct'] = round(($net / $authorized) * 100, 2);
        return $this->load->view('extension/payment/scanpay_order', $data);

    }
*/

fetch('index.php?route=extension/payment/scanpay/ajaxScanpayOrder&user_token=' + token + '&orderid=' + orderid)
    .then((res) => res.json()).then((o) => {
        buildTable(o);
        rev = o.rev;
    });


if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
} else {
    init();
}
