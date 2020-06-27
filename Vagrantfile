Vagrant.configure("2") do |config|
  # The most common configuration options are documented and commented below.
  # For a complete reference, please see the online documentation at
  # https://docs.vagrantup.com.

  config.vm.box = "ubuntu/xenial64"
  config.vm.hostname = "student"

  config.vm.network "forwarded_port", guest: 80, host: 8081 # webserver
  config.vm.network "forwarded_port", guest: 27017, host: 28017 # mongodb
  config.vm.network "private_network", ip: "192.168.30.10"

  config.vm.synced_folder ".", "/home/ubuntu/project"

  config.vm.provider :virtualbox do |vb|
      # vb.name = vagrant_root
      vb.name = "student_local_server"
      vb.gui = false
      vb.customize ["modifyvm", :id, "--cpus", 1]
      vb.customize ["modifyvm", :id, "--memory", 512]
  end

  config.vm.provision "shell", path: "./bootstrap.sh"
end
