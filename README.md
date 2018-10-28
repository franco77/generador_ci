# Generador de código de CodeIgniter
![Ejemplo de lo que el generador crea](https://i1.wp.com/parzibyte.me/blog/wp-content/uploads/2018/10/Aviso-de-mascota-agregada.png?resize=768,437&ssl=1)
## Ver en vivo
La página que se ve aquí:
http://cgfyemliexrl.byethost3.com/mascotas_generado/index.php/mascotas/
Fue generada por, valga la redundancia, este generador. Lo único que agregué fue el encabezado y pie
## Modo de uso
Aquí un ejemplo:

    include_once  __DIR__  .  "/Generador.php";
    
    $generador =  new  Generador("localhost",  "root",  "",  "mascotas");
    
    # Ninguna tabla a ignorar
    
    $generador->setTablasAIgnorar([]);
    
      
    
    # ¡Vamos allá!
    
    $generador->generar();

## Más información y detalles

He escrito un artículo sobre este generador, con más detalles e historia en: 
https://parzibyte.me/blog/2018/10/27/generador-codigo-modelo-vista-controlador-codeigniter/
Siéntete libre de visitarlo y comentar.