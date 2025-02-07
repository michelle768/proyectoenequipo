//Desarrollo de Software INSAMT

#include <WiFi.h>
#include <WiFiClientSecure.h>
#include "soc/soc.h"
#include "soc/rtc_cntl_reg.h"
#include "esp_camera.h"
#include <UniversalTelegramBot.h>
#include <ArduinoJson.h>
#include <Wire.h>

#define CAMERA_MODEL_AI_THINKER // Has PSRAM

//Reemplazar con los datos de tu red wifi
const char* ssid = "Adela";
const char* password = "Adela123";
String idChat = "6328600425";//ID_Chat se obtiene de telegram
//Token de Telegram BOT se obtenienen desde Botfather en telegram
String token = "5638409212:AAFCKLBy92MSl6nv7JhjeKfBa27aSsQV7MQ";
bool envioFoto = false;
WiFiClientSecure clientTCP;
UniversalTelegramBot bot(token, clientTCP);
//*PARA CAMARA MODELO AI THINKER
#define PWDN_GPIO_NUM     32
#define RESET_GPIO_NUM    -1
#define XCLK_GPIO_NUM      0
#define SIOD_GPIO_NUM     26
#define SIOC_GPIO_NUM     27
#define Y9_GPIO_NUM       35
#define Y8_GPIO_NUM       34
#define Y7_GPIO_NUM       39
#define Y6_GPIO_NUM       36
#define Y5_GPIO_NUM       21
#define Y4_GPIO_NUM       19
#define Y3_GPIO_NUM       18
#define Y2_GPIO_NUM        5
#define VSYNC_GPIO_NUM    25
#define HREF_GPIO_NUM     23
#define PCLK_GPIO_NUM     22


#define flashLed 4
bool flashEstado = LOW;
int estadoFlash = 0;
//Timbre
bool timbre = false;
#define buzzer 2
int tiempoMsg = 1000;   //tiempo medio entre escaneo de mensajes
long ultimoEscaneo;     //la Última vez que se realizó el escaneo de mensajes
void manejarMensajes(int nuevoMensajes);
String sendPhotoTelegram();
// Indica cuando se apretó el timbre
static void IRAM_ATTR pulsarTimbre(void * arg){
Serial.println("Timbre precionado!");
timbre = true;
}
void setup(){
WRITE_PERI_REG(RTC_CNTL_BROWN_OUT_REG, 0); 
Serial.begin(115200);
pinMode(buzzer, OUTPUT);
pinMode(flashLed, OUTPUT);
digitalWrite(flashLed, flashEstado);
WiFi.mode(WIFI_STA);
Serial.println();
Serial.print("Conectando a ");
Serial.println(ssid);
WiFi.begin(ssid, password);
clientTCP.setCACert(TELEGRAM_CERTIFICATE_ROOT); // Agregar certificado raíz para api.telegram.org
while (WiFi.status() != WL_CONNECTED) {
Serial.print(".");
delay(500);
bot.sendMessage(idChat, "Sistema preparado!!!, escribe /ayuda para ver las opciones", "");//Enviamos un mensaje a telegram para informar que el sistema está listo
}
Serial.println();
Serial.print("Dirección IP del módulo esp32cam: ");
Serial.println(WiFi.localIP());
camera_config_t config;
config.ledc_channel = LEDC_CHANNEL_0;
config.ledc_timer = LEDC_TIMER_0;
config.pin_d0 = Y2_GPIO_NUM;
config.pin_d1 = Y3_GPIO_NUM;
config.pin_d2 = Y4_GPIO_NUM;
config.pin_d3 = Y5_GPIO_NUM;
config.pin_d4 = Y6_GPIO_NUM;
config.pin_d5 = Y7_GPIO_NUM;
config.pin_d6 = Y8_GPIO_NUM;
config.pin_d7 = Y9_GPIO_NUM;
config.pin_xclk = XCLK_GPIO_NUM;
config.pin_pclk = PCLK_GPIO_NUM;
config.pin_vsync = VSYNC_GPIO_NUM;
config.pin_href = HREF_GPIO_NUM;
config.pin_sscb_sda = SIOD_GPIO_NUM;
config.pin_sscb_scl = SIOC_GPIO_NUM;
config.pin_pwdn = PWDN_GPIO_NUM;
config.pin_reset = RESET_GPIO_NUM;
config.xclk_freq_hz = 20000000;
config.pixel_format = PIXFORMAT_JPEG;
if(psramFound()){
config.frame_size = FRAMESIZE_UXGA;
config.jpeg_quality = 1;  //0-63 menor número significa mayor calidad
config.fb_count = 2;
} else {
config.frame_size = FRAMESIZE_SVGA;
config.jpeg_quality = 2;  //0-63 menor número significa mayor calidad
config.fb_count = 1;
}
// inicio de la cámara
esp_err_t err = esp_camera_init(&config);
if (err != ESP_OK) {
Serial.printf("El inicio de la cámara falló 0x%x", err);
delay(1000);
ESP.restart();
}
// Tamaño de cuadro desplegable para una mayor velocidad de cuadro inicial
sensor_t * s = esp_camera_sensor_get();
s->set_framesize(s, FRAMESIZE_CIF);  // UXGA|SXGA|XGA|SVGA|VGA|CIF|QVGA|HQVGA|QQVGA
// En el pin 13 se coloca el pulsador 
err = gpio_isr_handler_add(GPIO_NUM_13, &pulsarTimbre, (void *) 13);  
if (err != ESP_OK){
Serial.printf("hubo un error 0x%x \r\n", err); 
}
err = gpio_set_intr_type(GPIO_NUM_13, GPIO_INTR_POSEDGE);
if (err != ESP_OK){
Serial.printf("hubo un error 0x%x \r\n", err);
}
}
void loop(){
if (envioFoto){
Serial.println("Preparando foto");
sendPhotoTelegram(); 
if(estadoFlash == 1){
envioFoto = false; 
flashEstado = !flashEstado;
digitalWrite(flashLed, flashEstado);
estadoFlash = 0;
}
}
if(timbre){   //Hacemos sonar el buzzer en forma intermitente para emular el timbre

digitalWrite(buzzer, HIGH);
delay(1000);
digitalWrite(buzzer, LOW);
delay(1000);
digitalWrite(buzzer, HIGH);
delay(1000);
digitalWrite(buzzer, LOW);
delay(1000);

bot.sendMessage(idChat, "Alguien está tocando el timbre", "");
Serial.println("Alguien está tocando el timbre");
sendPhotoTelegram();
timbre = false;
}
if (millis() > ultimoEscaneo + tiempoMsg){
int nuevoMensajes = bot.getUpdates(bot.last_message_received + 1);
while (nuevoMensajes){
Serial.println("se obtuvo respuesta");
manejarMensajes(nuevoMensajes);
nuevoMensajes = bot.getUpdates(bot.last_message_received + 1);
}
ultimoEscaneo = millis();
}
}
String sendPhotoTelegram() {
const char* url = "api.telegram.org";
String todo = "";
String cuerpo = "";
camera_fb_t * fb = NULL;
fb = esp_camera_fb_get();  
if(!fb) {
Serial.println("Captura de cámara falló");
delay(1000);
ESP.restart();
return "Captura de cámara falló";
}  
Serial.println("Conectar a " + String(url));
if (clientTCP.connect(url, 443)) {
Serial.println("Conexión establecida");   
String cadena1 = "--rogerBit\r\nContent-Disposition: form-data; name=\"chat_id\"; \r\n\r\n" + idChat + "\r\n--rogerBit\r\nContent-Disposition: form-data; name=\"photo\"; filename=\"esp32-cam.jpg\"\r\nContent-Type: image/jpeg\r\n\r\n";
String cadena2 = "\r\n--rogerBit--\r\n";
uint16_t ima1 = fb->len;
uint16_t ima2 = cadena1.length() + cadena2.length();
uint16_t ima1y2 = ima1 + ima2;  
clientTCP.println("POST /bot"+token+"/sendPhoto HTTP/1.1");
clientTCP.println("Host: " + String(url));
clientTCP.println("Content-Length: " + String(ima1y2));
clientTCP.println("Content-Type: multipart/form-data; boundary=rogerBit");
clientTCP.println();
clientTCP.print(cadena1);  
uint8_t *fbBuf = fb->buf;
size_t fbLen = fb->len;
for (size_t n=0;n<fbLen;n=n+1024) {
if (n+1024<fbLen) {
clientTCP.write(fbBuf, 1024);
fbBuf += 1024;
}
else if (fbLen%1024>0) {
size_t remainder = fbLen%1024;
clientTCP.write(fbBuf, remainder);
}
}     
clientTCP.print(cadena2); 
esp_camera_fb_return(fb);    
int espera = 10000;   // 10 segundos
long tiempoInicial = millis();
boolean estado = false;  
while ((tiempoInicial + espera) > millis()){
Serial.print(".");
delay(100);      
while (clientTCP.available()) {
char c = clientTCP.read();
if (estado==true) cuerpo += String(c);        
if (c == '\n') {
if (todo.length()==0) estado=true; 
todo = "";
} 
else if (c != '\r')
todo += String(c);
tiempoInicial = millis();
}
if (cuerpo.length()>0) break;
}
clientTCP.stop();
Serial.println(cuerpo);
}
else {
cuerpo="Conexión a api.telegram.org falló.";
Serial.println("Conexión a api.telegram.org falló ");
}
return cuerpo;
}
void manejarMensajes(int nuevoMensajes){
Serial.print("Nuevo mensaje: ");
Serial.println(nuevoMensajes);
for (int i = 0; i < nuevoMensajes; i++){
// ID de chat del solicitante
String chat_id = String(bot.messages[i].chat_id);
if (chat_id != idChat){
bot.sendMessage(idChat, "Usuario no autorizado", "");
continue;
}   
// Imprime el mensaje recibido
String text = bot.messages[i].text;
Serial.println(text);
String fromName = bot.messages[i].from_name;
if (text == "/flash") {//Activa o desactiva el flash
flashEstado = !flashEstado;
digitalWrite(flashLed, flashEstado);
}
if (text == "/fotofh") {//Toma una foto con flash
envioFoto = true;
Serial.println("Solicitud de nueva foto");
flashEstado = !flashEstado;
digitalWrite(flashLed, flashEstado);
estadoFlash = 1;
}
if (text == "/foto") {//Toma una foto
envioFoto = true;
Serial.println("Solicitud de nueva foto");
}
if (text == "/ayuda")//Nos muestra la ayuda en telegram
{
String ayuda = "Bienvenido al sistema de timbre con foto con ESP32CAM " ".\n";
ayuda += "Estas son tus opciones.\n\n";
ayuda += "/foto : Toma una foto nueva \n";
ayuda += "/fotofh: saca una foto con flash y luego lo desactiva \n";
ayuda += "/flash : Activa el flash \n";
ayuda += "/ayuda: Imprime este mensaje \n";
ayuda += "Recuerda el sistema distingue entre mayuculas y minusculas \n";
bot.sendMessage(idChat, ayuda, "");
}
}
}
 
