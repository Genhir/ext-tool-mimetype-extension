cd ..
git clone https://github.com/jshttp/mime-db.git

sleep 1
cd ./mime-db

npm install --dev
sleep 1

npm install gnode
sleep 1

npm run fetch
sleep 1

npm run build
sleep 1

cd ../scripts
php build.phpt

echo "
Building finished.
"

read -n1 -r -p "Press any key to continue..." key