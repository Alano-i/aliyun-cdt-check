#构建docker镜像
#在终端执行 make docker
docker:
	docker build -t alanoo/aliyun-cdt-check:latest .

# 构建完成的镜像推送到 Docker Hub
dp:
	docker buildx build --platform linux/amd64,linux/arm64 -t alanoo/aliyun-cdt-check:latest --push .
