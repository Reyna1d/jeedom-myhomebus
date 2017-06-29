getDate = function() {
    var currentTime = new Date();
    var hours = currentTime.getHours();
    var minutes = currentTime.getMinutes();
    var seconds = currentTime.getSeconds();
    var month = currentTime.getMonth() + 1;
    var day = currentTime.getDate();
    var year = currentTime.getFullYear();
 
    if (hours<10) {hours ="0"+hours;}; 
    if (minutes<10) {minutes="0" +minutes;};
    if (seconds<10) {seconds="0" +seconds;};
    return day+"/"+month+"/"+year+" - "+hours+":"+minutes+":"+seconds+" ";
};
exports.getDate = getDate;

exports.merge_options = function(obj1,obj2){
    var obj3 = {};
    for (var attrname in obj1) { obj3[attrname] = obj1[attrname]; }
    for (var attrname in obj2) { obj3[attrname] = obj2[attrname]; }
    return obj3;
};

exports.AdrToObj = function(adr){
    //xy -> Amb : x, PL : y
    //xxyy -> Amb : xx, PL : yy 
    var Obj = {
        Amb:0,
        PL:0,
    };
    switch (adr.length){
        case 4 :
            Obj.Amb = parseInt(adr.substring(0,2));
            Obj.PL = parseInt(adr.substring(2,4));
            return Obj;
        break;
        case 2 :
            Obj.Amb = parseInt(adr.substring(0,1));
            Obj.PL = parseInt(adr.substring(1,2)); 
            return Obj;
        break;
        default :
            return false;
        break;      
    }   
};

exports.formatInt = function(n,nbChar){   
    if(!nbChar) nbChar=2;   
    str = ("000000" + n).slice(-nbChar);
    //console.log(n+' -> '+str);
    return str;
};


exports.calcPass = function (pass, nonce) {
    var flag = true;
    var num1 = 0x0;
    var num2 = 0x0;
    var password = parseInt(pass, 10);
    
    for (var c in nonce) {
        c = nonce[c];
        if (c!='0') {
            if (flag) num2 = password;
            flag = false;
        }
        switch (c) {
            case '1':
                num1 = num2 & 0xFFFFFF80;
                num1 = num1 >>> 7;
                num2 = num2 << 25;
                num1 = num1 + num2;
                break;
            case '2':
                num1 = num2 & 0xFFFFFFF0;
                num1 = num1 >>> 4;
                num2 = num2 << 28;
                num1 = num1 + num2;
                break;
            case '3':
                num1 = num2 & 0xFFFFFFF8;
                num1 = num1 >>> 3;
                num2 = num2 << 29;
                num1 = num1 + num2;
                break;
            case '4':
                num1 = num2 << 1;
                num2 = num2 >>> 31;
                num1 = num1 + num2;
                break;
            case '5':
                num1 = num2 << 5;
                num2 = num2 >>> 27;
                num1 = num1 + num2;
                break;
            case '6':
                num1 = num2 << 12;
                num2 = num2 >>> 20;
                num1 = num1 + num2;
                break;
            case '7':
                num1 = num2 & 0x0000FF00;
                num1 = num1 + (( num2 & 0x000000FF ) << 24 );
                num1 = num1 + (( num2 & 0x00FF0000 ) >>> 16 );
                num2 = ( num2 & 0xFF000000 ) >>> 8;
                num1 = num1 + num2;
                break;
            case '8':
                num1 = num2 & 0x0000FFFF;
                num1 = num1 << 16;
                num1 = num1 + ( num2 >>> 24 );
                num2 = num2 & 0x00FF0000;
                num2 = num2 >>> 8;
                num1 = num1 + num2;
                break;
            case '9':
                num1 = ~num2;
                break;
            case '0':
                num1 = num2;
                break;
        }
        num2 = num1;
    }
    return (num1 >>> 0).toString();
};

exports.isEmpty = function(obj) {
    // null and undefined are "empty"
    if (obj == null) return true;

    // Assume if it has a length property with a non-zero value
    // that that property is correct.
    if (obj.length > 0)    return false;
    if (obj.length === 0)  return true;

    // Otherwise, does it have any properties of its own?
    // Note that this doesn't handle
    // toString and valueOf enumeration bugs in IE < 9
    for (var key in obj) {
        if (hasOwnProperty.call(obj, key)) return false;
    }

    return true;
}


exports.sortHashTable = function(hashTable, key, removeKey) {
    hashTable = (hashTable instanceof Array ? hashTable : []);
    var newHashTable = hashTable.sort(function (a, b) {
        return (typeof(a[key]) === 'number' ?  a[key] - b[key] : a[key] > b[key]);
    });
    if (removeKey) {
        for (i in newHashTable) {
            delete newHashTable[i][key];
        }
    }
    return newHashTable;
};

exports.dtToTimestamp = function(dt){
	var date = new Date(dt);
	date.setMilliseconds(0);
	date.setSeconds(0);
	return Math.round(date.getTime()/1000);
};

