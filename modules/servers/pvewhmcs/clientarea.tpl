<div class="row">
	<div style="text-align : left;">
	</div>
	<div class="col col-md-12">
		<div class="row">
			<div class="col col-md-3">
				<div class="row">
					<div class="col col-md-12">
						<img src="/modules/servers/pvewhmcs/img/{$vm_config['vtype']}.png"/>
					</div>
				</div>			
				<div class="row">
					<div class="col col-md-12">
						<img src="/modules/servers/pvewhmcs/img/os/{$vm_config['ostype']}.png"/>
					</div>
				</div>
			</div>
			<div class="col col-md-2">
				<img src="/modules/servers/pvewhmcs/img/{$vm_status['status']}.png"/><br/>
				<span style="text-transform: uppercase"><strong><i>{$vm_status['status']}</i></strong></span><br/>
				Up:&nbsp;{$vm_status['uptime']}
			</div>
			<div class="col col-md-7">
				<div class="row">
					<script src="/modules/servers/pvewhmcs/js/CircularLoader.js"></script>
					<div class="col col-md-3" style="height:106px;">
						<div id="c1" class="circle" data-percent="{$vm_status['cpu']}"><strong>CPU</strong></div>
					</div>
					<div class="col col-md-3">
						<div id="c2" class="circle" data-percent="{$vm_status['memusepercent']}"><strong>RAM</strong></div>
					</div>
					<div class="col col-md-3">
						<div id="c3" class="circle" data-percent="{$vm_status['diskusepercent']}"><strong>Disk</strong></div>
					</div>
					<div class="col col-md-3">
						<div id="c4" class="circle" data-percent="{$vm_status['swapusepercent']}"><strong>Swap</strong></div>
					</div>
				</div>
				<script>
				$(document).ready(function() {
					$('.circle').each(function(){
						$(this).circularloader({
							progressPercent: $(this).attr("data-percent"),
							fontSize: "13px",
							radius: 30,
							progressBarWidth: 8,
							progressBarBackground: "#D6B1F9",
							progressBarColor: "#802DBC",
						});
					});
				});
				</script>
			</div>
		</div>
	</div>

	<table class="table table-bordered table-striped">
		<tr>
			<td><strong>Memory</strong> (RAM)</td>
			<td><strong>{$vm_config['memory']}MB</strong></td>
		</tr>
		<tr>
			<td><strong>Compute</strong> (CPU)</td>
			<td><strong>{$vm_config['cpu']}&nbsp;{$vm_config['cores']}&nbsp;core/s&nbsp;on&nbsp;{$vm_config['sockets']}&nbsp;socket/s
			</strong></td>
		</tr>
		<tr>
			<td><strong>Storage</strong> (SSD/HDD)</td>
			<td>
			{$rootfs=(","|explode:$vm_config['rootfs'])}
			{$disk=(","|explode:$vm_config['ide0'])}
			{$disk[1]}
			{$rootfs[1]}
			{($vm_config['scsi0']|replace:',':'<br/>')}
			{($vm_config['virtio0']|replace:',':'<br/>')}
			</td>
		</tr>
		<tr>
			<td><strong>OS</strong> (System Kernel)</td>
			<td><strong>{$vm_config['ostype']}</strong></td>
		</tr>
		<tr>
			<td><strong>IPv4</strong> (Networking)</td><td><strong>{$vm_config['ipv4']}</strong><br/>Mask:&nbsp;{$vm_config['netmask4']}<br/>Gateway:&nbsp;{$vm_config['gateway4']}</td>
		</tr>
		<tr>
			<td><strong>ipconfig</strong> (IPv4/v6)</td><td><strong>NIC #0</strong>: {$vm_config['ipconfig0']}<br><strong>NIC #1</strong>: {$vm_config['ipconfig1']}</td>
		</tr>
		<tr>
			<td><strong>NIC #0</strong> (IPv4)</td>
			<td>{($vm_config['net0']|replace:',':'<br/>'|replace:'=':': ')}</td>
		</tr>
		<tr>
			<td><strong>NIC #1</strong> (IPv6)</td>
			<td>{($vm_config['net1']|replace:',':'<br/>'|replace:'=':': ')}</td>
		</tr>
		<tr>
			<td><strong>Config</strong> (Tweaks)</td>
			<td>on-boot: {$vm_config['onboot']}</td>
		</tr>
		<tr>
			<td><strong>SSH Keys</strong> (Public)</td>
			<td>{$vm_config['sshkeys']}</td>
		</tr>
	</table>
	{if ($smarty.get.a eq 'vmStat')}
	<h4>VM Statistics</h4>
	<ul class="nav nav-tabs client-tabs" role="tab-list">
		<li class="active"><a id="dailytab" data-toggle="tab" role="tab" href="#dailystat">Daily</a></li>
		<li><a id="dailytab" data-toggle="tab" role="tab" href="#weeklystat">Weekly</a></li>
		<li><a id="dailytab" data-toggle="tab" role="tab" href="#monthlystat">Monthly</a></li>
		<li><a id="dailytab" data-toggle="tab" role="tab" href="#yearlystat">Yearly</a></li>
	</ul>
	<div class="tab-content admin-tabs">
		<div id="dailystat" class="tab-pane active">
			<img src="data:image/png;base64,{$vm_statistics['cpu']['day']}"/>
			<img src="data:image/png;base64,{$vm_statistics['maxmem']['day']}"/>
			<img src="data:image/png;base64,{$vm_statistics['netinout']['day']}"/>
			<img src="data:image/png;base64,{$vm_statistics['diskrw']['day']}"/>
		</div>
		<div id="weeklystat" class="tab-pane">
			<img src="data:image/png;base64,{$vm_statistics['cpu']['week']}"/>
			<img src="data:image/png;base64,{$vm_statistics['maxmem']['week']}"/>
			<img src="data:image/png;base64,{$vm_statistics['netinout']['week']}"/>
			<img src="data:image/png;base64,{$vm_statistics['diskrw']['week']}"/>
		</div>
		<div id="monthlystat" class="tab-pane">
			<img src="data:image/png;base64,{$vm_statistics['cpu']['month']}"/>
			<img src="data:image/png;base64,{$vm_statistics['maxmem']['month']}"/>
			<img src="data:image/png;base64,{$vm_statistics['netinout']['month']}"/>
			<img src="data:image/png;base64,{$vm_statistics['diskrw']['month']}"/>
		</div>
		<div id="yearlystat" class="tab-pane">
			<img src="data:image/png;base64,{$vm_statistics['cpu']['year']}"/>
			<img src="data:image/png;base64,{$vm_statistics['maxmem']['year']}"/>
			<img src="data:image/png;base64,{$vm_statistics['netinout']['year']}"/>
			<img src="data:image/png;base64,{$vm_statistics['diskrw']['year']}"/>
		</div>
	</div>
	{/if}

	
</div>
