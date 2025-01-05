<style>
 .img-fluid {
 max-width: 100%;
 height: auto;
 display: block;
 margin: 20px auto;
 }

 .nav-tabs {
 border-bottom: 1px solid #ddd;
 }

 .nav-tabs .nav-item {
 margin-bottom: -1px;
 }

 .nav-tabs .nav-link {
 border-radius: 0;
 padding: 10px 15px;
 color: #007bff;
 }

 .nav-tabs .nav-link.active {
 color: #fff;
 background-color: #007bff;
 border-color: #007bff;
 }

 .tab-content {
 padding: 20px;
 }

 /* Responsive design */
 @media (max-width: 768px) {
 .nav-tabs .nav-link {
 padding: 8px 12px;
 }

 .img-fluid {
 margin: 10px 0;
 }
 }

 /* Optimize image arrangement */
 .tab-pane img {
 margin-bottom: 15px;
 }
 </style><div class="row">
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
			<td><strong>IP</strong> (Addressing)</td><td><strong>{$vm_config['ipv4']}</strong><br/>Subnet Mask:&nbsp;{$vm_config['netmask4']}<br/>Gateway:&nbsp;{$vm_config['gateway4']}</td>
		</tr>
		<tr>
			<td><strong>OS/etc</strong> (System)</td>
			<td>Kernel:&nbsp;{$vm_config['ostype']}</td>
		</tr>
		<tr>
			<td><strong>Compute</strong> (CPU)</td>
			<td>{$vm_config['sockets']}&nbsp;socket/s,&nbsp;{$vm_config['cores']}&nbsp;core/s<br />
			Emulation: {$vm_config['cpu']}</td>
		</tr>
		<tr>
			<td><strong>Memory</strong> (RAM)</td>
			<td>{$vm_config['memory']}MB</td>
		</tr>
		<tr>
			<td><strong>NIC</strong> (Interface #1)</td>
			<td>{($vm_config['net0']|replace:',':'<br/>')}</td>
		</tr>
		<tr>
			<td><strong>NIC</strong> (Interface #2)</td>
			<td>{$vm_config['net1']}</td>
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
	</table>
	{if ($smarty.get.a eq 'vmStat')}
	<h4>VM Statistics</h4>
<ul class="nav nav-tabs client-tabs" role="tablist">
    <li class="active">
        <a id="daily-tab" data-toggle="tab" role="tab" href="#dailystat">Daily</a>
    </li>
    <li>
        <a id="weekly-tab" data-toggle="tab" role="tab" href="#weeklystat">Weekly</a>
    </li>
    <li>
        <a id="monthly-tab" data-toggle="tab" role="tab" href="#monthlystat">Monthly</a>
    </li>
    <li>
        <a id="yearly-tab" data-toggle="tab" role="tab" href="#yearlystat">Yearly</a>
    </li>
</ul>
<div class="tab-content admin-tabs">
    <div id="dailystat" class="tab-pane active">
        <img class="img-fluid" src="data:image/png;base64,{$vm_statistics['cpu']['day']}" alt="Daily CPU Usage"/>
        <img class="img-fluid" src="data:image/png;base64,{$vm_statistics['maxmem']['day']}" alt="Daily Memory Usage"/>
        <img class="img-fluid" src="data:image/png;base64,{$vm_statistics['netinout']['day']}" alt="Daily Network Usage"/>
        <img class="img-fluid" src="data:image/png;base64,{$vm_statistics['diskrw']['day']}" alt="Daily Disk Usage"/>
    </div>
    <div id="weeklystat" class="tab-pane">
        <img class="img-fluid" src="data:image/png;base64,{$vm_statistics['cpu']['week']}" alt="Weekly CPU Usage"/>
        <img class="img-fluid" src="data:image/png;base64,{$vm_statistics['maxmem']['week']}" alt="Weekly Memory Usage"/>
        <img class="img-fluid" src="data:image/png;base64,{$vm_statistics['netinout']['week']}" alt="Weekly Network Usage"/>
        <img class="img-fluid" src="data:image/png;base64,{$vm_statistics['diskrw']['week']}" alt="Weekly Disk Usage"/>
    </div>
    <div id="monthlystat" class="tab-pane">
        <img class="img-fluid" src="data:image/png;base64,{$vm_statistics['cpu']['month']}" alt="Monthly CPU Usage"/>
        <img class="img-fluid" src="data:image/png;base64,{$vm_statistics['maxmem']['month']}" alt="Monthly Memory Usage"/>
        <img class="img-fluid" src="data:image/png;base64,{$vm_statistics['netinout']['month']}" alt="Monthly Network Usage"/>
        <img class="img-fluid" src="data:image/png;base64,{$vm_statistics['diskrw']['month']}" alt="Monthly Disk Usage"/>
    </div>
    <div id="yearlystat" class="tab-pane">
        <img class="img-fluid" src="data:image/png;base64,{$vm_statistics['cpu']['year']}" alt="Yearly CPU Usage"/>
        <img class="img-fluid" src="data:image/png;base64,{$vm_statistics['maxmem']['year']}" alt="Yearly Memory Usage"/>
        <img class="img-fluid" src="data:image/png;base64,{$vm_statistics['netinout']['year']}" alt="Yearly Network Usage"/>
        <img class="img-fluid" src="data:image/png;base64,{$vm_statistics['diskrw']['year']}" alt="Yearly Disk Usage"/>
    </div>
</div>
	{/if}

	
</div>
