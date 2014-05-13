Vagrant.configure("2") do |config|
	config.vm.hostname = "postbot"
	config.vm.box = "hashicorp/precise64"

	config.vm.network :private_network, ip: "192.168.3.10"
	config.vm.hostname = "postbot"
	config.hostsupdater.aliases = ["local.postbot.com"]

	config.vm.provision :shell, :path => "bootstrap.sh"
end
