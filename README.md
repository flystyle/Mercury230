
# PHP class for Mercury 230 electricity meter

![Mercury 230](https://www.incotexcom.ru/files/styles/a/adaptive-image/public/em/images/catalogue/mercury-230art_4.jpg?itok=rpoSOrds)

This class allows send the specified commands to a meter connected with an IrDA or RS485 adapter to a ser2net router. Some commands can be found in the comments, a complete description of Mercury 230 protocol is available [here](https://www.incotexcom.ru/support/docs/protocol).

Usage
-----

```php
// Router or adapter IP address and port, meter address (the last two digits of the serail number)
$meter = new Mercury230('tcp://ip', 'port', 'address');

// Open connection
$meter->open();

// Send commands to the meter

// Get the meter information
$info = $meter->get_meter();

// Get energy at the moment
$moment = $meter->get_moment();

// Get stored energy (daily and total)
$stored = $meter->get_stored();

// Send custom command
$command = $meter->send('0800');

...

// Close connection
$meter->close();
```

Home Assistant integration
-------------

There are several ways to get counter data in Home Assistant. For example, you can do this via JSON response and automation in Node-RED. Sample json.php response:
![JSON](https://i.imgur.com/3e4wRjo.png)

Another way is to publish data to an MQTT topic. See how to do it in mqtt.php.
Don't forget to install dependencies with:
> composer install

An example of a cron job to publish data to MQTT:

moment values every 10 minutes
```sh
*/10 * * * *    root   /usr/bin/php /path/to/mqtt.php
```

stored values once a day
```sh
11 0 * * *    root   /usr/bin/php /var/www/smart.home/public/energy/mqtt.php stored
```

It's also possible to use wget to run the script:
```sh
11 0 * * *    root   /usr/bin/wget -O- http://smart.home/energy/mqtt.php?mode=stored >> /dev/null
```

After that you can add sensors to your Home Assistant configuration.yaml
```yaml
sensor:
    - platform: mqtt
      name: "Energy T1"
      state_topic: "homeassistant/sensor/energy/stored"
      device_class: energy
      unit_of_measurement: "kWh"
      value_template: "{{ (value_json.t1 | int * 0.001) | round(2) }}"

    - platform: mqtt
      name: "Energy Volt 1"
      state_topic: "homeassistant/sensor/energy/moment"
      device_class: voltage
      unit_of_measurement: "V"
      value_template: "{{ (value_json.U1 | int * 0.01) | round(2) }}"
      ...
```

A complete list of sensors can be found in **homeassistant_config.yaml**

If you did everything right, it should look like this:
![homeassistant](https://i.imgur.com/yfiW4cs.png)
