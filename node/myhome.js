/*
	MyHome
	---------
	Module by Reynald Ligiardi

*/

var net = require('net');
var fs = require('fs');
var util = require('util');
var events 	= require("events");
var debug = true;
var debugDate = true;
var common = require('./common');
const log = require('simple-node-logger').createSimpleLogger({timestampFormat:'YYYY-MM-DD HH:mm:ss'});
const RETRY_INTERVAL = 10000;
const STATE_UNCONNECTED = 0;
const STATE_CONNECTING = 1;
const STATE_LOGGING_IN = 2;
const STATE_CONNECTED = 3;
const ACK = '*#*1##';
const NACK = '*#*0##'; 
const START_CONFIG = '*99*0##';
const START_COMMAND = '*99*9##';
const START_EVENT = '*99*1##';
const SCAN_ALL = '*#1001*0*13##';
const SCAN_NO_CONF = '*#1001*0*13#0##';
const SCAN_CONF = '*#1001*0*13#1##';

/***************************************************
*            CREATION DE L'OBJET MyHome            *
****************************************************
_ip : ip de la gateway
_port : port de la gateway
_mdp : Mot de passe de connexion à la gateway
*/

function myHome(_ip,_port,_mdp,debuglevel) {
	if (debuglevel) log.setLevel(debuglevel);

	events.EventEmitter.call(this);	
	var self = this;
	this.delayVR = 30000; //Delai (en ms) ou l'actionneur du VR se coupe (et donc envoi un état 0)
	this.stateGatewayCde = STATE_UNCONNECTED;
	this.stateGateway = STATE_UNCONNECTED;
	this.stateGatewayConfig = STATE_UNCONNECTED;
	this.stateGatewayConfigState = 0;
	this.retry_Interval = RETRY_INTERVAL;
	this.socketEvent = null;
	this.socketCde = null;
	this.socketConfig = null;
	this.port = _port;
	this.ip = _ip;
	this.mdp = _mdp;
	this.reconnect = true; //Doit-on se reconnecter automatiquement
	this.nbReconnect = -1; //Nb de tentative de reconnection auto (-1 : infini)
	this.nbTentativew = 1;	
	this.states = {};
	this.config = {};
	
	
	/*********************************************************************************/
	/*  						 	SCAN Configuration 								 */
	/*********************************************************************************/
	this.scan = function( _scanmode, _callback){
		self.stateGatewayConfig = STATE_UNCONNECTED;
		self.stateGatewayConfigState = 0;
		var scan_state = 0;
		self.socketConfig = net.connect({host:self.ip,port:self.port});
		self.socketConfig.on('data', function(data) {
	  		var reponse = data.toString();
	  		//log.debug('<- '+reponse);
	  		switch (self.stateGatewayConfig) { 
			  	case STATE_UNCONNECTED: //Pas encore connecté, on recois donc la premère réponse
			  		if (reponse==ACK) { //Connection acceptée			  			
			  			log.info('Gateway CONFIG Connected');
			  			self.stateGatewayConfig = STATE_CONNECTING;			  			
			  			self.socketConfig.write(START_CONFIG); //Initialisation du socket en mode config			
			  		}
			  	break;
	  	
				case STATE_CONNECTING : //Socket en cours d'initialisation
			  		if (reponse==ACK) { //Socket intialisé
			  			log.info('Gateway CONFIG Initialized (no mdp)');
			  			self.stateGatewayConfig = STATE_CONNECTED;			  			
			  			self.socketConfig.write(SCAN_ALL);
			  		}else{	//Pas ok, on tente de se loger avec le mdp
			  			m = reponse.match(/\*#(\d+)##/);
			  			if (m===null) {
			  				/* no match ? */
			  				//self.emit("error", '');
			  			} else {							
							/* nonce is first captured string from regexp */
							var p = common.calcPass(self.mdp, m[1]);					
							self.stateGatewayConfig = STATE_LOGGING_IN;
							log.debug('cde -> *#'+p+'##');
							self.socketConfig.write('*#'+p+'##');
						}
			  		}
			  	break;

				case STATE_LOGGING_IN :
					if (reponse==ACK) {
						log.info('Gateway CONFIG Initialized');
						self.stateGatewayConfig = STATE_CONNECTED;																
			  			self.socketConfig.write(SCAN_ALL);
					}
				break;

			  	case STATE_CONNECTED :			  		
			  		self.handleDataScan(reponse);
				  	// if(_callback){
				  	// 	_callback(reponse);
				  	// }
			  	break;	  	
	  		}
		});
	
		self.socketConfig.on('connect', function() {	  	
		});
	
		self.socketConfig.on('end', function() {		  	
		    self.stateGatewayConfig = STATE_UNCONNECTED;
		    self.socketConfig = false;  	
		});
	
		self.socketConfig.on('close', function() {
			self.stateGatewayConfig = STATE_UNCONNECTED;
			self.socketConfig = false;  	
		});
	
		self.socketConfig.on('disconnect', function(){
		   	self.stateGatewayConfig = STATE_UNCONNECTED;
		   	self.socketConfig = false;
		});
	
		self.socketConfig.on('error', function(err) {
			self.stateGatewayConfig = STATE_UNCONNECTED;
		});
	};

	this.handleDataScan = function(_reps){
		//console.log(this.scan_state);
		var reps = _reps.toString();
		while (reps.length>0) {
			var m = reps.match(/(\*.+?##)(.*)/);
			rep = m[1];  //packet à traiter
			reps = m[2]; //Reste des packets
			if(rep!=ACK){
				switch (self.stateGatewayConfigState){
					case 0: //1ere pass du scan, on scan tous les devices
						if (/\*#1001\*0\*13\*(\d+)##/g.exec(rep)){ //adresse MAC d'un périf
							log.debug(rep);
							var m2 = rep.match(/\*#(\d+)\*(\d+)\*(\d+)\*(\d+)##/);
							var mac = parseInt(m2[4],10);	
							this.config[mac]=null;							
						} else{
							log.debug(rep);
						}
						break;
				}
			}else{
				switch (self.stateGatewayConfigState){
					case 0: //1ere pass du scan, on scan tous les devices
						console.log('Scan all devices OK');
						self.stateGatewayConfigState++;
						// for(var mac in this.config) {
						// 	console.log(mac);
						// 	self.socketConfig.write('*1001*10#'+mac+'*0##');
						// }
						var arr = Object.keys(this.config);
						log.debug(arr[0]);
						self.socketConfig.write('*1001*10#'+arr[0]+'*0##');
						break;
				}
			}			
		};
	};

	/*********************************************************************************/
	/*   Traitement des TRAMES a envoyer pour prendre en compte une customisation    */
	/*********************************************************************************/
	this.custoCde = function(cdeOpen){
		//CUSTO CDE OpenWebNet
		log.debug('CUSTO CDE');
		if (/\*1\*([0-9][0-9]{0,1})\*([0-9]?\d{1})([0-9]?\d{1})/g.exec(cdeOpen)){ //CUSTO WHO=1
			var tabRes = cdeOpen.split(/[\*#]/); //On décompose la réponse
			var what = parseInt(tabRes[2]);
			log.debug('what : '+what);
			var adresse = tabRes[3];										
			switch (what) { 
				case 99: //Switch de l'état du PL : Commande de la forme *1*99*WHERE##
					log.debug('CUSTO CDE : 99');
					var obj = common.AdrToObj(adresse);
					var id='01'+common.formatInt(obj.Amb)+common.formatInt(obj.PL);	
					var what = 1;
					if(this.states[id]!= undefined){
						if(this.states[id].status==1){														
							what = 0;
						}
					}
					cdeOpen = '*1*'+what+'*'+adresse+'##'
					break;
			}
		}
		return cdeOpen;
	};

	/*********************************************************************************/
	/*  Connection au socket commande du gateway OpenWebNet et envoie d'une cde OPEN */
	/*********************************************************************************/
	this.sendCde = function(cdeOpen,callback){		
        if(self.stateGatewayCde==STATE_CONNECTED) { //SI LE SOCKET EST TOUJOURS OUVERT            
            try {
                self.socketCde.write(self.custoCde(cdeOpen));   
            } catch (e) { //Erreur lors de l'envoie de la commande
                self.stateGateway = STATE_UNCONNECTED;
                if(callback) callback(e,'');				  	
            }
        }else{
            self.stateGatewayCde = STATE_UNCONNECTED;
            self.socketCde = net.connect({host:self.ip,port:self.port});
            self.socketCde.on('data', function(data) {
                var reponse = data.toString();
                //log.debug('<- '+reponse);
                switch (self.stateGatewayCde) { 
                    case STATE_UNCONNECTED: //Pas encore connecté, on recois donc la premère réponse
                        if (reponse==ACK) { //Connection acceptée			  			
                            log.info('Socket CDE Connected');
                            self.stateGatewayCde = STATE_CONNECTING;
                            try {
                                self.socketCde.write(START_COMMAND); //Initialisation du socket en mode event    
                            } catch (e) { //Erreur lors de l'envoie de la commande
                                self.stateGateway = STATE_UNCONNECTED;
                                if(callback) callback(e,'');				  	
                            }			  			  			
                        }
                    break;
            
                    case STATE_CONNECTING : //Socket en cours d'initialisation
                        if (reponse==ACK) { //Socket intialisé
                            log.info('Socket CDE Initialized (no mdp)');
                            self.stateGatewayCde = STATE_CONNECTED;			  			
                            if (cdeOpen){
                                log.debug(cdeOpen);
                                try {  
                                self.socketCde.write(self.custoCde(cdeOpen));
                                } catch (e) { //Erreur lors de l'envoie de la commande
                                    self.stateGateway = STATE_UNCONNECTED;
                                    if(callback) callback(e,'');				  	
                                }  
                            };
                        }else{	//Pas ok, on tente de se loger avec le mdp
                            m = reponse.match(/\*#(\d+)##/);
                            if (m===null) {
                                /* no match ? */
                                //self.emit("error", '');
                            } else {							
                                /* nonce is first captured string from regexp */
                                var p = common.calcPass(self.mdp, m[1]);					
                                self.stateGatewayCde = STATE_LOGGING_IN;
                                log.debug('cde -> *#'+p+'##');							
                                try {  
                                self.socketCde.write('*#'+p+'##');
                                } catch (e) { //Erreur lors de l'envoie de la commande
                                    self.stateGateway = STATE_UNCONNECTED;
                                    if(callback) callback(e,'');				  	
                                } 
                            }
                        }
                    break;

                    case STATE_LOGGING_IN :
                        if (reponse==ACK) {
                            log.info('Socket CDE Initialized');
                            self.stateGatewayCde = STATE_CONNECTED;																
                            if (cdeOpen){
                                log.debug('Socket CDE  send : '+cdeOpen);			  				
                                try { 
                                	self.socketCde.write(self.custoCde(cdeOpen));
                                } catch (e) { //Erreur lors de l'envoie de la commande
                                    self.stateGateway = STATE_UNCONNECTED;
                                    if(callback) callback(e,'');				  	
                                }
                            };
                        }
                    break;

                    case STATE_CONNECTED :			  					  			  		
                        if(callback){
                            callback(false,reponse);
                        }
                    break;	  	
                }            
            });
	
            self.socketCde.on('connect', function() {	  	
            });
        
            self.socketCde.on('end', function() {
                log.info('Socket CDE END');		  	
                self.stateGatewayCde = STATE_UNCONNECTED;
                self.socketCde = false;  	
            });
        
            self.socketCde.on('close', function() {
                log.info('Socket CDE CLOSE');
                self.stateGatewayCde = STATE_UNCONNECTED;
                self.socketCde = false;  	
            });
        
            self.socketCde.on('disconnect', function(){
                log.info('Socket CDE DISCONNECT');
                self.stateGatewayCde = STATE_UNCONNECTED;
                self.socketCde = false;
            });
        
            self.socketCde.on('error', function(err) {
                self.stateGateway = STATE_UNCONNECTED;
				log.error('ERROR : '+err);
            });
        }
	};


	/*********************************************************/
	/*  Connection au socket evenement du gateway OpenWebNet */
	/*********************************************************/
	this.login = function() {
		self.stateGateway = STATE_UNCONNECTED;
		log.info('Connect to Gateway ('+self.ip+":"+self.port+')');
		self.socketEvent = net.connect({host:self.ip,port:self.port});

		self.socketEvent.on('data', function(data) {
  			var reponse = data.toString();
		  //log.debug('<- '+reponse);
		  switch (self.stateGateway) { 
		  	case STATE_UNCONNECTED: //Pas encore connecté, on recois donc la premère réponse
		  		if (reponse==ACK) { //Connection acceptée
		  			log.info('Gateway Connected');
		  			self.stateGateway = STATE_CONNECTING;
		  			self.socketEvent.write(START_EVENT); //Initialisation du socket en mode event			
		  		}
		  	break;
		  	
		  	case STATE_CONNECTING : //Socket en cours d'initialisation
		  		if (reponse==ACK) { //Socket intialisé
		  			log.info('Gateway Initialized without Login');
		  			self.stateGateway = STATE_CONNECTED;
		  			self.emit("online");
		  			self.sendCde('*#1*0##');
		  		}else{	//Pas ok, on tente de se loger avec le mdp
		  			m = reponse.match(/\*#(\d+)##/);
		  			if (m===null) {
		  				/* no match ? */
		  				self.emit("error", '');
		  			} else {							
						/* nonce is first captured string from regexp */
						var p = common.calcPass(self.mdp, m[1]);					
						self.stateGateway = STATE_LOGGING_IN;
						log.debug('-> *#'+p+'##');
						self.socketEvent.write('*#'+p+'##');
					}
		  		}
		  	break;
		  	
			case STATE_LOGGING_IN :
				if (reponse==ACK) {
					log.info('Gateway Initialized with Login');
					self.stateGateway = STATE_CONNECTED;					
					self.emit("online");
					
					self.sendCde('*#1*0##');
					//self.socketEvent.write('*#1*0##'); //Check de tous les états des PL et VR			
				}
			break;

		  	case STATE_CONNECTED :
		  		//log.debug('<- '+reponse);
		  		self.handleData(reponse);		  		
		  	break;
		  }
		});

		self.socketEvent.on('end', function() {
		  	log.info('Gateway client end');
		    self.stateGateway = STATE_UNCONNECTED;
		  	self.reconnecteSocket();
		});

		self.socketEvent.on('close', function() {
			log.info('Gateway client close');
			self.stateGateway = STATE_UNCONNECTED;
			self.reconnecteSocket();
		});

		self.socketEvent.on('disconnect', function(){
			log.info('Gateway client disconnected');
			self.stateGateway = STATE_UNCONNECTED;		   	
		   	self.reconnecteSocket();
		});

		self.socketEvent.on('error', function(err) {
			log.error('Gateway Error '+err.code);
			self.emit("error", err.code);
			self.stateGateway = STATE_UNCONNECTED;		  	
		});		
	};

	this.handleData = function(_reps){
		var reps = _reps.toString();
		while (reps.length>0) {
			var m = reps.match(/(\*.+?##)(.*)/);
			/* first packet is m[1], rest is m[2] */
			rep = m[1];
			reps = m[2];	

			///////////////////////////////////////////
			//		changement de status Lumières	 //
			///////////////////////////////////////////	
			if (/\*1\*[0-1]\*([0-9]?\d{1})([0-9]?\d{1})/g.exec(rep)){
				var tabRes = rep.split(/[\*#]/); //On décompose la réponse
				var status = parseInt(tabRes[2]);
				var adresse = tabRes[3];
				var obj = common.AdrToObj(adresse);
				var id='01'+common.formatInt(obj.Amb)+common.formatInt(obj.PL);
				log.debug('PL '+id);
				if(this.states[id]!= undefined){ //Si on trouve une info précédente concernant cette lumière
					var oldStatus = this.states[id].status;
					var oldTs = this.states[id].ts; 
					log.debug('PL : ' + oldStatus+' -> '+status);
					if(oldStatus!=status){ //Si le status a changé par rapport a la dernière fois
						var ts = Date.now(); //Timestamp courant
						this.states[id].status = status;
						this.states[id].ts = Date.now();						
						self.emit("statePL", {
							amb : obj.Amb,
							pl : obj.PL,
							states : this.states[id],
						});
					}
				}else{
					this.states[id] = {status:status,ts:Date.now()};
					self.emit("statePL", {
						amb : obj.Amb,
						pl : obj.PL,
						states : this.states[id],
					});
				};				
									
			} 

			///////////////////////////////////////////
			//		   changement de status VR   	 //
			///////////////////////////////////////////	
			//  0 : inconnue
			//  1 : Ouvert
			//  2 : Fermé
			//  3 : mi-ouvert/mi-fermé 
			// -1 : en cours d'ouverture
			// -2 :	en cours de fermeture		
			else if (/\*2\*[0-2]\*([0-9]?\d{1})([0-9]?\d{1})/g.exec(rep)){ //changement de status VR
				var tabRes = rep.split(/[\*#]/); //On décompose la réponse 
				var status = parseInt(tabRes[2]);
				var adresse = tabRes[3];
				var obj = common.AdrToObj(adresse);
				var id='02'+common.formatInt(obj.Amb)+common.formatInt(obj.PL);
				log.debug('VR '+id);	
				if(this.states[id]!= undefined){ //Si on trouve une info précédente concernant ce VR
					var oldStatus = this.states[id].status;
					var oldTs = this.states[id].ts; 
					log.debug('VR : '+ oldStatus+' -> '+status);					
					if(oldStatus!=status){ // Si le status a changé par rapport a la dernière fois
						var ts = Date.now(); // Timestamp courant
						if(oldStatus==0){ // Si le VR est dans une position inconnue
							log.debug('VR : position inconnue');
							//le VR doit être en MVT ou fixe si on recois 0, dans ce cas, on ne sait toujours pas
							//la position du VR, on laisse donc 0
							this.states[id].status = -status; // - 0 = 0 

						}else if(oldStatus>0){//Si le VR est fixe
							if(status!=0){ //Si on recois autre chose qu'un STOP, sinon on ne fait rien
								log.debug('VR : arret manuel');									
								this.states[id].status = -status;
								this.states[id].ts = ts;
							}else{
								log.debug('VR : arret manuel alors que VR fixe, on ne fait rien');
								this.states[id].ts = ts;									
							}

						}else{ //le VR est en mouvement
							log.debug('VR : Volet en mvt');	
							if(status==0){ //Si c'est un arret du VR
								log.debug('VR : '+ts+' - '+oldTs+' -> '+(ts-oldTs));
								var newStatus = 0;
								//Il faut faire la distinction entre un STOP volontaire ou un STOP auto de l'actionneur
								if(ts>oldTs+this.delayVR){ //C'est un passage à 0 de l'actionneur auto																			
									newStatus = 0 - oldStatus;
									log.debug('VR : arret auto -> '+newStatus);
								}else{//C'est un stop volontaire
									log.debug('VR : arret manuel');									
									newStatus = 3; //3 = mi-ouvert/mi-fermé												
								}
								//this.states[id] = { status : newStatus, ts : ts};
								this.states[id].status = newStatus;
								this.states[id].ts = ts;
							}else{ //C'est un changement de direction

							}
						};
						//this.states[id] = {status:-status,ts:ts};
						self.emit("stateVR", {
							amb : obj.Amb,
							pl : obj.PL,
							states : this.states[id],
						});	
					}else{
						log.debug('VR : Pas de changement de status.');
					}
				}else{ //pas encore d'info
					this.states[id] = { status : status, ts : Date.now()};			
				}				
			}

			///////////////////////////////////////////
			//				Commande CEN+			 //
			///////////////////////////////////////////	
			else if (/\*25\*2[1-8]#([1-9]|[12][0-9]|3[01])\*([1-9][0-9]{0,2}|1[0-9]{3}|20[0-3][0-9]|204[0-7])##/g.exec(rep)){ 
				//Commande CEN+ : *25*action#pushbutton*object##
				//	action :
				// 		21 : Short pressure ( < 0.5 seconds )
				// 		22 : Start of extended pressure ( >= 0.5 seconds )
				// 		23 : Extended pressure
				// 		24 : Released after an extended pressure
				// 		25 : Rotary selector in slow clockwise rotation
				// 		26 : Rotary selector in fast clockwise rotation
				// 		27 : Rotary selector in slow counter-clockwise rotation
				// 		28 : Rotary selector in fast counter-clockwise rotation
				//	pushbutton : virtual pressure of the push button n N value = [0-31]
				//	address : push button virtual address = [0-2047]
				log.debug('CEN+'); 	
				var tabRes = rep.split(/[\*#]/); //On décompose la réponse 
				self.emit("CEN+", {
					address : tabRes[4],
					action : tabRes[2],
					pushbutton : tabRes[3],
					trame : rep,
				});
			}

			///////////////////////////////////////////
			//			Commande BASIC CEN			 //
			///////////////////////////////////////////	
			else if (/\*2\*[0-2]\*[1-9][1-9]/g.exec(rep)){
				//Commande BASIC CEN : *15*pushbutton*address##
				//	pushbutton : virtual pressure of the push button n N value = [0-31]
				//	address : push button virtual address
				log.debug('BASIC CEN'); 
				var tabRes = rep.split(/[\*#]/); //On décompose la réponse
				self.emit("CEN", {
					address : tabRes[3],
					action : null,
					pushbutton : tabRes[2],
				});
			}
			
			///////////////////////////////////////////
			//			Commande EVOLVED CEN		 //
			///////////////////////////////////////////	
			else if (/\*2\*[0-2]\*[1-9][1-9]/g.exec(rep)){
				//Commande EVOLVED CEN : *15*pushbutton#action*address##
				//	action :
				// 		1 : Release after short pressure
				// 		2 : Release after an extended pressure
				// 		3 : Extended pressure
				//	pushbutton : virtual pressure of the push button n N value = [0-31]
				//	address : push button virtual address
				log.debug('EVOLVED CEN'); 
				var tabRes = rep.split(/[\*#]/); //On décompose la réponse
				self.emit("CEN", {
					address : tabRes[4],
					action : tabRes[3],
					pushbutton : tabRes[2],
				});
			}

			///////////////////////////////////////////
			//	  	  Commande de DIAGNOSTIQUE		 //
			///////////////////////////////////////////	
			else if (/^\*#1001/g.exec(rep)){ //Toutes les Commande de DIAGNOSTIQUE
				self.emit("diag", rep);
			}

			///////////////////////////////////////////
			//	  	  Commande de CONTACT SEC		 //
			///////////////////////////////////////////	
			else if (/^\*25\*3(\d)#[0-1]\*3(\d+)/g.exec(rep)){ //Toutes les Commande de CONTACT SEC
				log.debug('DRYCONTACT'); 
				var tabRes = rep.split(/[\*#]/); //On décompose la réponse
				self.emit("drycontact", {
					value : tabRes[2]=='1',
                	address : tabRes[3],
				});
				
			}


			///////////////////////////////////////////
			//			Commande Inter  			 //
			///////////////////////////////////////////	
			else if (/^\*1\*1000/g.exec(rep)){
				//Commande DIM : *1*1000#action*address##
				//	action : type of pressure
				//		30 : up btn
				//		31 : down btn 
				//	address : virtual address
				var tabRes = rep.split(/[\*#]/); //On décompose la réponse 
				var action = parseInt(tabRes[2]);
				var adresse = tabRes[3];								
				var obj = common.AdrToObj(adresse);
				var id='01'+common.formatInt(obj.Amb)+common.formatInt(obj.PL);
				log.debug('DIM : '+action+' '+obj.Amb+' - '+obj.PL);
				self.emit("DIM", {
					amb : obj.Amb,
					pl : obj.PL,
					action : action,			
				});
			}

			///////////////////////////////////////////
			//	  	 	 Message non traité			 //
			///////////////////////////////////////////	
			else {
				self.emit("message", rep);
			};
		};

	};

	this.reconnecteSocket = function() {
		if(self.reconnect){
			log.info('Attempt to reconnect to Gateway ('+self.ip+":"+self.port+')');
			setTimeout(function() {			
				self.socketEvent.connect({host:self.ip,port: self.port});
			}, self.retry_Interval);
		};
	};

	//this.login(_ip, _port, _mdp);
	this.login();
};

util.inherits(myHome, events.EventEmitter);	
exports.Connect = myHome;


