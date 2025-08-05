import React from "react";
import { Modal } from "react-bootstrap";
import styled, { createGlobalStyle } from "styled-components";
import Faq from "../../assets/img/faq.png";
import { RiQuestionAnswerFill } from "react-icons/ri";
import { BsFillQuestionDiamondFill } from "react-icons/bs";
import {
  CustomAccordion,
  AccordionHeader,
  AccordionContent,
  Tab,
  TabWrapper,
} from "components";
import { useState } from "react";
import { TabContainer } from "components/Popup/sendQuote/style";
import { useEffect } from "react";
import { useDispatch, useSelector } from "react-redux";
import { getFaq } from "modules/Home/home.slice";
import _ from "lodash";
import { useLocation } from "react-router";
import { useMediaPredicate } from "react-media-hook";

const FaqModal = (props) => {
  const { faq } = useSelector((state) => state.home);
  const [eventKey, setEventKey] = useState(false);
  const [isActive, setIsActive] = useState("general");

  const dispatch = useDispatch();

  const questions =
    !_.isEmpty(faq) && faq?.content ? JSON.parse(faq.content) : [];

  const generalQuestionsOfCar = questions.filter(
    (q) => q.category === "car_general_questions"
  );
  const generalQuestionsOfBike = questions.filter(
    (q) => q.category === "bike_general_questions"
  );
  const generalQuestionsOfCv = questions.filter(
    (q) => q.category === "cv_general_questions"
  );

  const generalQuestionsOfVehicle = questions.filter(
    (q) => q.category === "vehicle_general_questions"
  );
  const claimsOfCar = questions.filter((q) => q.category === "car_claims");
  const claimsOfBike = questions.filter((q) => q.category === "bike_claims");
  const claimsOfCv = questions.filter((q) => q.category === "cv_claims");
  const claimsOfVehicle = questions.filter(
    (q) => q.category === "vehicle_claims"
  );

  const location = useLocation();
  const loc = location.pathname ? location.pathname.split("/") : "";

  const lessthan767 = useMediaPredicate("(max-width: 767px)");

  //car ins
  const CarInsCover = questions.filter((q) => q.category === "car_ins_cover");
  const carInsPrem = questions.filter((q) => q.category === "car_ins_prem");
  //bike ins
  const BikeInsCover = questions.filter((q) => q.category === "bike_ins_cover");
  const BikeInsPrem = questions.filter((q) => q.category === "bike_ins_prem");
  //cv ins
  const CVInsCover = questions.filter((q) => q.category === "bike_ins_cover");
  const cVInsPrem = questions.filter((q) => q.category === "bike_ins_prem");
  //vehicle ins
  const VehicleInsCover = questions.filter(
    (q) => q.category === "vehicle_ins_cover"
  );
  const VehicleInsPrem = questions.filter(
    (q) => q.category === "vehicle_ins_prem"
  );

  useEffect(() => {
    dispatch(getFaq());
  }, [dispatch]);

  const filteredCondition =
    isActive === "general"
      ? loc.includes("car")
        ? generalQuestionsOfCar
        : loc.includes("bike")
        ? generalQuestionsOfBike
        : loc.includes("cv")
        ? generalQuestionsOfCv
        : generalQuestionsOfVehicle
      : isActive === "carIns"
      ? loc.includes("car")
        ? CarInsCover
        : loc.includes("bike")
        ? BikeInsCover
        : loc.includes("cv")
        ? CVInsCover
        : VehicleInsCover
      : isActive === "carInsPrem"
      ? loc.includes("car")
        ? carInsPrem
        : loc.includes("bike")
        ? BikeInsPrem
        : loc.includes("cv")
        ? cVInsPrem
        : VehicleInsPrem
      : loc.includes("car")
      ? claimsOfCar
      : loc.includes("bike")
      ? claimsOfBike
      : loc.includes("bike")
      ? claimsOfCv
      : claimsOfVehicle;

  return (
    <Modal
      {...props}
      size="lg"
      aria-labelledby="contained-modal-title-vcenter"
      centered
      backdrop={"static"}
      keyboard={false}
    >
      <Modal.Header closeButton>
        <HeadContainer>
          <Head>
            <Icon src={Faq} alt="faq" />
            <Header>Frequently Asked Questions</Header>
          </Head>
          <TabContainer>
            <TabWrapper style={{ padding: "0" }} className="tabWrappers">
              <Tab
                isActive={Boolean(isActive === "general")}
                onClick={() => setIsActive("general")}
                className="shareTab"
                shareTab="shareTab"
              >
                {lessthan767 ? "GQ" : "General Questions"}
              </Tab>
              <Tab
                isActive={Boolean(isActive === "carIns")}
                onClick={() => setIsActive("carIns")}
                className="shareTab"
                shareTab="shareTab"
              >
                {loc.includes("car")
                  ? lessthan767
                    ? "CIC"
                    : "Car Insurance Covers"
                  : loc.includes("bike")
                  ? lessthan767
                    ? "BIC"
                    : "Bike Insurance Covers"
                  : loc.includes("bike")
                  ? lessthan767
                    ? "CVIC"
                    : "CV Insurance Covers"
                  : lessthan767
                  ? "VIC"
                  : "Vehicle Insurance Covers"}
              </Tab>
              <Tab
                isActive={Boolean(isActive === "carInsPrem")}
                onClick={() => setIsActive("carInsPrem")}
                className="shareTab"
                shareTab="shareTab"
              >
                {loc.includes("car")
                  ? lessthan767
                    ? "CIP"
                    : "Car Insurance Premium"
                  : loc.includes("bike")
                  ? lessthan767
                    ? "BIP"
                    : "Bike Insurance Premium"
                  : loc.includes("cv")
                  ? lessthan767
                    ? "CVIP"
                    : "CV Insurance Premium"
                  : lessthan767
                  ? "VIP"
                  : "Vehicle Insurance Premium"}
              </Tab>
              <Tab
                isActive={Boolean(isActive === "claims")}
                onClick={() => setIsActive("claims")}
                className="shareTab"
                shareTab="shareTab"
              >
                {loc.includes("car")
                  ? lessthan767
                    ? "CMICI"
                    : "Claims Made In Car Insurance"
                  : loc.includes("bike")
                  ? lessthan767
                    ? "CMIBI"
                    : "Claims Made In Bike Insurance"
                  : loc.includes("cv")
                  ? lessthan767
                    ? "CMICVI"
                    : "Claims Made In CV Insurance"
                  : lessthan767
                  ? "CMIVI"
                  : "Claims Made In VI"}
              </Tab>
            </TabWrapper>
          </TabContainer>
        </HeadContainer>
      </Modal.Header>
      <Modal.Body>
        <Content>
          {!_.isEmpty(questions) ? (
            <Questions>
              {filteredCondition.map((accordion) => (
                <CustomAccordion
                  key={accordion.question}
                  noPadding
                  eventKey={accordion.question}
                  setEventKey={setEventKey}
                  id={accordion.question}
                >
                  <AccordionHeader>
                    <Question>
                      <BsFillQuestionDiamondFill className="messageIcon" />
                      <Q1>{accordion.question}</Q1>
                    </Question>
                  </AccordionHeader>
                  <AccordionContent>
                    <div style={{ textAlign: "justify" }}>
                      <RiQuestionAnswerFill className="answerIcon" />
                      <Answer>
                        {typeof accordion?.answer === "string" &&
                        accordion.answer.includes("$")
                          ? accordion.answer.replace(/\$/g, "➥")
                          : accordion.answer}
                      </Answer>
                    </div>
                  </AccordionContent>
                </CustomAccordion>
              ))}
            </Questions>
          ) : _.isEmpty(questions) ? (
            <h1
              style={{
                display: "flex",
                justifyContent: "center",
                alignItems: "center",
                color: "gray",
              }}
            >
              Loading...
            </h1>
          ) : (
            questions &&
            questions.length ===
              0(
                <h1
                  style={{
                    display: "flex",
                    justifyContent: "center",
                    alignItems: "center",
                    color: "gray",
                  }}
                >
                  No Question Found
                </h1>
              )
          )}
        </Content>
      </Modal.Body>
      <GlobalStyle />
    </Modal>
  );
};

export default FaqModal;

const GlobalStyle = createGlobalStyle`
     .modal-header {
        border-bottom: none !important;
        padding: 30px 30px 0px 40px;
    }
    .modal-header .close {
       padding: 0rem 1rem;
    } 
    .modal-content {
      width: 100% !important;
      min-height: 85vh !important;
    }
    .modal.show .modal-dialog {
      display: flex;
      justify-content: center;
      align-items: center;
    }
    .modal {
      height: auto !important;
    }
    .messageIcon {
      margin-top: 5px;
      font-size: 20px;
    }
    .answerIcon {
      margin-right: 10px;
      margin-top: 5px;
      font-size: 20px;
    }
    .card-header {
      padding: 0.75rem 0rem !important;
    }
    .modal-body {
      height: 70vh;
      overflow-y: auto;
      scrollbar-width: thin;
    }
    @media only screen and (max-width: 767px) {
      
      .modal-header {
        padding: 30px 30px 0px 30px;
      }
      .modal-header .close {
        padding: 0px !important;
      } 
      .modal-dialog {
        width: 95% !important;
      }
    }
`;

const HeadContainer = styled.div`
  width: 100%;
  display: flex;
  flex-direction: column;
  gap: 30px;

  .tabWrappers {
    position: unset;
    margin-left: -12px;
    margin-bottom: 12px;
    box-shadow: rgba(149, 157, 165, 0.2) 0px 8px 24px;
    width: max-content;
  }
  .shareTab {
    border: none !important;
    border-radius: 20px;
    @media only screen and (max-width: 767px) {
      font-size: 16px !important;
    }
  }
`;

const Head = styled.div`
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 15px;
`;

const Icon = styled.img`
  width: 50px;
  height: 50px;
`;

const Content = styled.div`
  padding: 10px 30px;
`;
const Q1 = styled.p`
  font-weight: bold;
  margin-bottom: 0px !important;
`;

const Header = styled.h2`
  font-size: 22px;
  font-weight: bold;
  @media only screen and (max-width: 767px) {
    font-size: 14px;
  }
`;

const Questions = styled.div`
  margin-bottom: 20px;
`;
const Question = styled.div`
  display: flex;
  gap: 10px;
`;

const Answer = styled.small`
  white-space: pre-line;
`;
