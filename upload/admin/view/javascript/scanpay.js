$(document).ready(function () {
    function q(sel) { return document.querySelector(sel); }
    function qall(sel) { return document.querySelectorAll(sel); }
    function numcmp(a, b) { return a - b; }
    var statusArr = q('#scanpay--captureonorderstatus--input').value.split(',');
    statusArr = statusArr.map(function(x) { return parseInt(x); }).sort(numcmp);
    document.addEventListener('click', function() {
        $('#scanpay--captureonorderstatus--dropdown').removeClass('scanpay--show');
    });

    q('#scanpay--captureonorderstatus--add').addEventListener('click', function(e) {
        q('#scanpay--captureonorderstatus--dropdown').classList.toggle('scanpay--show');
        e.stopPropagation();
    });

    function statusIndex(status) {
        return statusArr.findIndex(function(id) { return id === parseInt(status); })
    }

    function mkStatus(id, name, dropdownElem) {
        var li = document.createElement('li');
        li.textContent = name;
        var span = document.createElement('span');
        span.textContent = '\u00D7';
        span.addEventListener('click', function (e) {
            li.parentNode.removeChild(li);
            statusArr.splice(statusIndex(id), 1);
            dropdownElem.classList.remove('scanpay--usedstatus');
            q('#scanpay--captureonorderstatus--input').value = statusArr.sort(numcmp).join(',');
        });
        li.dataset.id = id;
        li.appendChild(span);
        q('#scanpay--captureonorderstatus--list').append(li);
        dropdownElem.classList.add('scanpay--usedstatus');
    }
    /* Sort dropdown items */
    var dropdownItems = qall('#scanpay--captureonorderstatus--dropdown li');
    var dropdownItemsArr = [];
    for (var i = 0; i < dropdownItems.length; i++) {
        dropdownItemsArr.push(dropdownItems[i].cloneNode(true));
    }
    dropdownItemsArr.sort(function (a, b) {
        return parseInt(a.dataset.id) - parseInt(b.dataset.id);
    });
    var dropdownFrag = document.createDocumentFragment();
    dropdownItemsArr.forEach(function (el) {
        dropdownFrag.appendChild(el);
        if (statusIndex(el.dataset.id) >= 0) {
            mkStatus(el.dataset.id, el.textContent, el);
        }
    });
    q('#scanpay--captureonorderstatus--dropdown').innerHTML = '';
    q('#scanpay--captureonorderstatus--dropdown').appendChild(dropdownFrag);
    q('#scanpay--captureonorderstatus--dropdown').addEventListener('click', function(e) {
        if (event.target.matches('li')) {
            var opt = event.target;
            if (statusIndex(opt.dataset.id) >= 0) { return; }
            mkStatus(opt.dataset.id, opt.textContent, opt);
            statusArr.push(parseInt(opt.dataset.id));
            q('#scanpay--captureonorderstatus--input').value = statusArr.sort(numcmp).join(',');
        }
    });


});
