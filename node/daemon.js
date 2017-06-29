var net = require('net');
var fs = require('fs');
var request = require('request');
var myHome = require('./myhome');
var jsonfile = require('jsonfile')
const log = require('simple-node-logger').createSimpleLogger({timestampFormat:'YYYY-MM-DD HH:mm:ss'});
var urlJeedom = '';
var ipGateway = '';
var portGateway = '';
var mdpGateway = '';
var enable_log = '';
var portSocket = '55006'

var serveurSocket;
global.raccourcis = {};

process.argv.forEach(function(val, index, array) {
	switch ( index ) {
		case 2 : urlJeedom = val; break;		
		case 3 : ipGateway = val; break;
		case 4 : portGateway = val; break;
		case 5 : mdpGateway = val; break;
		case 6 : loglevel = val; break;
		case 7 : enable_log = val; break;
		case 8 : portSocket = val; break;
	}
});

if (loglevel) log.setLevel(loglevel);

//Lecture fichier des raccourci CEN
log.info('Lecture raccourcis CEN ...');
jsonfile.readFile('cen.json', function(err, obj) {
  if(err) {
		log.error('Error 2 : '+err);				
	}else{
		global.raccourcis = obj;	
		console.log(global.raccourcis);
		log.info('Lecture raccourcis CEN OK');				
	}
})

//logit.log('urlJeedom : ' + urlJeedom);

//Creation du socket
var server = net.createServer(function (socket) {
	socket.on('data', function (data) {
		var message = JSON.parse(data);
		log.debug('MyHomeBus Socket : message = '+JSON.stringify(message));
		switch (message.action) {
			case 'sendcde':
				if(gateway) gateway.sendCde(message.cde);
				break;
			case 'setRaccourci':
				var modif = false;
				//console.log('setRaccourcis');
				//console.log(global.raccourcis);
				if(!global.raccourcis[message.cdeCenP]){
					//console.log('cenp non trouvé');
					modif = true;
					global.raccourcis[message.cdeCenP]={};
				};
				//console.log(global.raccourcis[message.cdeCenP][message.id]);
				if(!global.raccourcis[message.cdeCenP][message.id]){
					//console.log('id non trouvé');
					modif = true;
					global.raccourcis[message.cdeCenP][message.id] = {};
				}
				if(global.raccourcis[message.cdeCenP][message.id]!=message.cde){
					modif = true;
					global.raccourcis[message.cdeCenP][message.id] = message.cde;
				}
				

				//console.log('resultat : ')
				//console.log(global.raccourcis);
				if(modif){
					log.info('Sauvegarde raccourcis CEN ...');
					jsonfile.writeFile('cen.json', global.raccourcis, function (err) {
						log.error('Error 3: '+err);	
					})
				}else{
					log.info('pas de modif raccourcis CEN ...');
				};
				break;
			
			case 'delRaccourci':
				var modif = false;
				//console.log('delRaccourcis');
				//console.log(global.raccourcis);
				if(global.raccourcis){
					for (var cenp in global.raccourcis) {
						//console.log(global.raccourcis[cenp]);
						if(global.raccourcis[cenp][message.id]){
							//console.log('supp');
							//console.log(global.raccourcis[cenp][message.id]);
							modif = true;
							delete global.raccourcis[cenp][message.id]

						}
						//for (var id in global.raccourcis[cenp]) {						
						//	console.log(global.raccourcis[cenp][id]);
						//};
					};
				}
				//console.log('resultat : ')
				//console.log(global.raccourcis);
				if(modif){
					log.info('Sauvegarde raccourcis CEN ...');
					jsonfile.writeFile('cen.json', global.raccourcis, function (err) {
						log.error('Error 4: '+err);
					})
				}else{
					log.info('pas de modif raccourcis CEN ...');
				};
				break;
		}		
	});
});
server.listen(portSocket);
server.on('listening',function(){
	log.info('MyHomeBus Socket démarré '+server.address().address+':'+server.address().port);	
});
server.on('connection', function(socket) { //This is a standard net.Socket
	var remoteAdress = socket.remoteAddress;
	var remotePort = socket.remotePort;
	log.debug('MyHomeBus Socket -> Client connected : ' + remoteAdress +':'+ remotePort);
//	serveurSocket = new JsonSocket(socket); //Now we've decorated the net.Socket to be a JsonSocket
//	serveurSocket.on('message', function(message) {
//		logit.log('MyHomeBus Socket : message = '+JSON.stringify(message));
//    	switch (message.action) {
//			case 'sendcde':
//
//			break;
//		};
//	});
});
server.on('error', function (err) {
	log.error('MyHomeBus Socket -> ' + err.code);
});


//Connexion MyHome
var gateway = new myHome.Connect(ipGateway,portGateway,mdpGateway,loglevel);

gateway.on('online', function() {
	log.debug("MyHomeBus Socket : Gateway Online ");
});

gateway.on('error', function(ex) {
	log.error("MyHomeBus Socket : Erreur : "+ex);
});

gateway.on('message', function(msg) {
	log.debug('EMIS : ' + msg);
//	logit.log(urlJeedom);
//	jeeApi = urlJeedom + "&trame="+msg;
//	request(jeeApi, function (error, response, body) {
//		if (!error && response.statusCode == 200) {
//			logit.log('Return OK from Jeedom');
//		}else{
//			logit.log(error);			
//		}
//	});
});

//gateway.on('diag', function(msg) {
//  	logit.log(msg);
//});

gateway.on('statePL', function(msg) {
	//logit.log(JSON.stringify(msg));
	jeeApi = urlJeedom + "&statePL="+encodeURIComponent(JSON.stringify(msg));
	log.debug('MyHomeBus Socket : Changement de status PL (amb: '+msg.amb+', PL: '+msg.pl+', status: '+msg.states.status+')');
	//log.debug(jeeApi);
	request(jeeApi, function (error, response, body) {
		if (!error && response.statusCode == 200) {
			log.debug('Return OK from Jeedom');
		}else{
			log.error('Error 5 : '+error+' ('+response.statusCode+')');	
		}
	});
});

gateway.on('stateVR', function(msg) {
	//logit.log(JSON.stringify(msg));
	jeeApi = urlJeedom + "&stateVR="+encodeURIComponent(JSON.stringify(msg));
	log.debug('MyHomeBus Socket : Changement de status VR (amb: '+msg.amb+', PL: '+msg.pl+', status: '+msg.states.status+')');
	log.debug(jeeApi);
	request(jeeApi, function (error, response, body) {
		if (!error && response.statusCode == 200) {
			log.debug('Return OK from Jeedom');
		}else{
			log.error('Error 6: '+error+' ('+response.statusCode+')');	
		}
	});
});

gateway.on('CEN+', function(msg) {
	log.debug('CEN+ (adress: '+msg.address+', action: '+msg.action+', pushbutton: '+msg.pushbutton+')');
	//console.log(msg.trame);
	//Verification raccourcis CEN+
	if(global.raccourcis[msg.trame]){
		for (var id in global.raccourcis[msg.trame]) {
			//console.log(global.raccourcis[msg.trame][id]);
			if(gateway) gateway.sendCde(global.raccourcis[msg.trame][id]);
		}
	}
	jeeApi = urlJeedom + "&cenplus="+encodeURIComponent(JSON.stringify(msg));
	log.debug(jeeApi);
	request(jeeApi, function (error, response, body) {
		if (!error && response.statusCode == 200) {
			log.debug('Return OK from Jeedom');
		}else{
			log.error('Error 7 : '+error+' ('+response.statusCode+')');	
		}
	});
});

gateway.on('CEN', function(msg) {
	log.debug('CEN (adress: '+msg.address+', action: '+msg.action+', pushbutton: '+msg.pushbutton+')');
	jeeApi = urlJeedom + "&cen="+encodeURIComponent(JSON.stringify(msg));
	log.debug(jeeApi);
	request(jeeApi, function (error, response, body) {
		if (!error && response.statusCode == 200) {
			log.debug('Return OK from Jeedom');
		}else{
			log.error('Error 8: '+error+' ('+response.statusCode+')');	
		}
	});
});

gateway.on('drycontact', function(msg) {
	log.debug('DRYCONTACT (adress: '+msg.address+', value: '+msg.value+')');
	jeeApi = urlJeedom + "&drycontact="+encodeURIComponent(JSON.stringify(msg));
	log.debug(jeeApi);
	request(jeeApi, function (error, response, body) {
		if (!error && response.statusCode == 200) {
			log.debug('Return OK from Jeedom');
		}else{
			log.error('Error 9 : '+error+' ('+response.statusCode+')');		
		}
	});
});
