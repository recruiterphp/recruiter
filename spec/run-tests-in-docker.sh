docker build -t easywelfare/recruiter-testing-image  .
docker run -it --rm --name recruiter-testing-$RANDOM easywelfare/recruiter-testing-image
