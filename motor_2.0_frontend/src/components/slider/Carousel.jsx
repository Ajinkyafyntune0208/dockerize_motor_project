import React from "react";
import Slider from "react-slick";
import styled, { createGlobalStyle } from "styled-components";
import "slick-carousel/slick/slick.css";
import "slick-carousel/slick/slick-theme.css";
import { useSelector } from "react-redux";
import { reloadPage } from "utils";

const Carousel = () => {
  const { temp_data } = useSelector((state) => state.proposal);
  const settings = {
    dots: true,
    infinite: false,
    slidesToShow: 1,
    slidesToScroll: 1,
    centerMode: true,
    mobileFirst: true,
    className: "center",
    centerPadding: "60px",
    initialSlide: 1,
    responsive: [
      {
        breakpoint: 360,
        settings: {
          slidesToShow: 1,
          slidesToScroll: 1,
          centerPadding: "20px",
        },
      },
      {
        breakpoint: 400,
        settings: {
          slidesToShow: 1,

          slidesToScroll: 1,
          centerPadding: "50px",
        },
      },
    ],
  };

  return (
    <>
      <Slider {...settings}>
        <div>
          <Card
            onClick={() =>
              reloadPage("https://health.renewbuy.com/input/basic-details")
            }
            card1
            className="active"
          >
            <CardIcon
              src={`${
                import.meta.env.VITE_BASENAME !== "NA"
                  ? `/${import.meta.env.VITE_BASENAME}`
                  : ""
              }/assets/images/healthIns.png`}
            />
            <MainText>Health Insurance</MainText>
            <small>You can earn</small>
            <br />
            <h5>₹ 1200*</h5>
            <Button>Get Quote</Button>
          </Card>
        </div>
        <div>
          <Card slide2 className="active">
            <CardIcon
              src={`${
                import.meta.env.VITE_BASENAME !== "NA"
                  ? `/${import.meta.env.VITE_BASENAME}`
                  : ""
              }/assets/images/life.png`}
            />
            <MainText>Life Insurance</MainText>
            <small>You can earn</small>
            <br />
            <h5>₹ 4000*</h5>
            <Button>Get Quote</Button>
          </Card>
        </div>
        <div>
          <Card
            onClick={() =>
              reloadPage(
                `${window.location.origin}${
                  import.meta.env.VITE_BASENAME !== "NA"
                    ? `/${import.meta.env.VITE_BASENAME}`
                    : ``
                }/${
                  Number(temp_data?.productSubTypeId) === 1
                    ? "car"
                    : Number(temp_data?.productSubTypeId) === 2
                    ? "bike"
                    : "cv"
                }/lead-page`
              )
            }
            className="active"
          >
            <CardIcon
              motor
              src={`${
                import.meta.env.VITE_BASENAME !== "NA"
                  ? `/${import.meta.env.VITE_BASENAME}`
                  : ""
              }/assets/images/motor.png`}
            />
            <MainText>Motor Insurance</MainText>
            <small>Issue another</small>
            <Button>Get Quote</Button>
          </Card>
        </div>
      </Slider>
      <GlobalStyle />
    </>
  );
};

export default Carousel;

const GlobalStyle = createGlobalStyle`
.slick-list {
  overflow: visible;
}
.slick-active{
  transition: .4s;
  .active{
    background: linear-gradient(to right, #FFB76B 0%, #FFA73D 30%, #FF7C00 60%, #FF7F04 100%);
  }
}

.slick-slide.slick-center div {
  transform: scale(1.02);
}
`;

const Card = styled.div`
  position: relative;
  height: 210px;
  width: 190px;
  color: #fff;
  background: #292949;
  transition: 1s;
  margin: auto;
  padding: 10px;
  border-radius: 15px;
  background: #292949;
`;

const MainText = styled.p`
  margin-bottom: 5px !important;
`;

const Button = styled.button`
  position: absolute;
  bottom: 0px;
  right: 50px;
  background: linear-gradient(69.83deg, #3877d6 0%, #3877d6 100%);
  color: #fff;
  border-radius: 10px 13px 0px 0px;
  border: 0px;
`;

const CardIcon = styled.img`
  height: 36px;
  padding: ${(props) => props.motor && "4px 0px"};
  width: ${(props) => (props.motor ? "60px" : "36px")};
  margin: 15px auto;
`;
