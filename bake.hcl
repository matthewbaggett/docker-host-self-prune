group "default" {
  targets = [
    "pruner"
  ]
}

target "pruner" {
  context = "."
  dockerfile = "Dockerfile"
  platforms = ["arm64","amd64"]
  tags = ["matthewbaggett/pruner",]
}
