<?

# Copyright (c) 2017-2018 Adrian Frühwirth
# License: MIT

require_once("config.inc");

#syslog(LOG_NOTICE, "usb_modeswitch: switching E3276 to modem device");
mwexec("/usr/local/sbin/usb_modeswitch -v 12d1 -p 1f01 -J -R -W");
mwexec("/bin/sleep 2");

#syslog(LOG_NOTICE, "upnpc: updating NAT rules");

# retrieve all UDP/TCP NAT rules to WAN interface with WANIP as target
$nat_rules = array_filter($config['nat']['rule'], function ($foo)
{
	return(
		$foo['interface'] === 'wan' &&
		$foo['destination']['network'] === 'wanip' &&
		preg_match('/tcp|udp/', $foo['protocol'])
	);
});

# populate $ports array with all port aliases, $ports[$alias_name]=$port_number
foreach(
	array_filter($config['aliases']['alias'], function ($foo) { return($foo['type'] === 'port'); }) as &$port)
{
	$ports[$port['name']] = $port['address'];
}

function lookup_port_aliases($s_ports)
{
	$a_ports = array_map(function ($port) { global $ports; return isset($ports[$port]) ? $ports[$port] : $port; }, explode(" ", $s_ports));
	while (preg_grep('/[^0-9 ]/', $a_ports))
	{
		$a_ports = lookup_port_aliases(implode(" ", $a_ports));
	}
	return $a_ports;
}

# process NAT rules
$upnpc_cmd = array("/usr/local/bin/upnpc -r");
foreach ($nat_rules as $nat_rule)
{
	$nat_ports    = $nat_rule['destination']['port'];
	$nat_protocol = $nat_rule['protocol'];
	foreach (lookup_port_aliases($nat_ports) as $nat_port)
	{
		if (preg_match('/tcp/', $nat_protocol))
			array_push($upnpc_cmd, $nat_port, "tcp");
		if (preg_match('/udp/', $nat_protocol))
			array_push($upnpc_cmd, $nat_port, "udp");
	}
}

mwexec(implode(" ", $upnpc_cmd));

?>
