docker build -t recruiterphp/recruiter-testing-image  .
docker run -it --rm --name recruiter-testing-$RANDOM recruiterphp/recruiter-testing-image
