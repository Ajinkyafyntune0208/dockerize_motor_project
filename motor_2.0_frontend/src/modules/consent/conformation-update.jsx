import { Switch } from "components";
import { postCommunicationPreference } from "modules/Home/home.slice";
import React from "react";
import { useState } from "react";
import { useEffect } from "react";
import { useDispatch, useSelector } from "react-redux";
import { useLocation } from "react-router-dom";
import styled from "styled-components";
import swal from "sweetalert";

const ConformationUpdates = () => {
  const { communicationPreference } = useSelector((state) => state.home);
  const dispatch = useDispatch();
  const location = useLocation();

  const query = new URLSearchParams(location.search);

  const email = query.get("email");
  const mobile_no = query.get("mobile_no");

  const [switchValues, setSwitchValues] = useState({
    call: communicationPreference?.on_call ? true : false,
    sms: communicationPreference?.on_sms ? true : false,
    email: communicationPreference?.on_email ? true : false,
    whatsapp: communicationPreference?.on_whatsapp ? true : false,
  });

  const handleSwitchChange = (name) => {
    setSwitchValues((prevSwitchValues) => ({
      ...prevSwitchValues,
      [name]: !prevSwitchValues[name],
    }));
  };

  const isSelected =
    switchValues?.call ||
    switchValues?.sms ||
    switchValues?.email ||
    switchValues.whatsapp;

  const handleSubscription = (
    actionType,
    confirmationTitle,
    successMessage
  ) => {
    if (!isSelected) {
      swal("Info", `You have select at least one preference`, "info");
      return;
    }

    const data = {
      mode: "set",
      email: email,
      mobile_no: mobile_no,
      on_call: actionType === switchValues.call ? "Y" : "N",
      on_sms: switchValues.sms ? "Y" : "N",
      on_email: switchValues.email ? "Y" : "N",
      on_whatsapp: switchValues.whatsapp ? "Y" : "N",
    };

    swal({
      title: confirmationTitle,
      icon: "warning",
      buttons: {
        cancel: "Cancel",
        catch: {
          text: "Confirm",
          value: "confirm",
        },
      },
    }).then((confirm) => {
      if (confirm) {
        dispatch(postCommunicationPreference(data));
        swal(successMessage, `You have been ${actionType}d.`, "success");
      } else {
        swal("Cancelled", `Your subscription status remains the same.`, "info");
      }
    });
  };

  useEffect(() => {
    dispatch(
      postCommunicationPreference({
        mode: "fetch",
        email: "yogi@gmail.com",
        mobile_no: "9768154422",
      })
    );
  }, [dispatch]);

  return (
    <Wrapper>
      <Container>
        <Content>
          Are you absolutely certain you wish to opt out of receiving our
          updates?{" "}
        </Content>
        <SwitchButton>
          <Item>
            <Question>Do you want to get update on call?</Question>
            <Switch
              consent
              value={switchValues.call}
              onChange={() => handleSwitchChange("call")}
            />
          </Item>
          <Item>
            <Question>Do you want to get update on sms?</Question>
            <Switch
              consent
              value={switchValues.sms}
              onChange={() => handleSwitchChange("sms")}
            />
          </Item>
          <Item>
            <Question>Do you want to get update on email?</Question>
            <Switch
              consent
              value={switchValues.email}
              onChange={() => handleSwitchChange("email")}
            />
          </Item>
          <Item>
            <Question>Do you want to get update on whatsapp?</Question>
            <Switch
              consent
              value={switchValues.whatsapp}
              onChange={() => handleSwitchChange("whatsapp")}
            />
          </Item>
        </SwitchButton>
        <Buttons>
          <Button
            onClick={() =>
              handleSubscription("subscribe", "Subscribe Now?", "Subscribed")
            }
            // disabled={!isSelected}
          >
            Save
          </Button>
        </Buttons>
        <WarningText>
          <span>Disclaimer : </span>{" "}
          <span>
            Transactional communications will persists regardless of individual
            preference
          </span>
        </WarningText>
      </Container>
    </Wrapper>
  );
};

export default ConformationUpdates;

const Wrapper = styled.div`
  max-width: 690px;
  display: flex;
  justify-content: center;
  align-items: center;
  height: 100vh;
  padding: 20px;
  text-align: center;
  margin: auto;
  @media (max-width: 768px) {
    align-items: start;
    width: auto;
  }
`;

const Container = styled.div`
  @media only screen and (min-width: 768px) {
    background-color: #f7f7f7;
    padding: 43px 23px;
    border-radius: 7px;
  }
`;

const Content = styled.div`
  font-size: 28px;
  font-weight: 500;
  margin: 40px;
  color: ${({ theme }) => theme.regularFont?.fontColor || "rgb(74, 74, 74)"};
  @media only screen and (max-width: 768px) {
    font-size: 24px;
    margin: 0px 0 15px 0;
    padding: 5px;
    width: auto;
  }
`;
const WarningText = styled.div`
  font-size: 10px;
  margin-top: 20px;
  span:first-child {
    font-weight: 600;
  }
  span:last-child {
    color: gray;
  }
  @media only screen and (max-width: 768px) {
    text-align: left;
  }
`;
const Buttons = styled.div`
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 50px;
  margin-top: 20px;
  @media only screen and (max-width: 768px) {
    flex-direction: column;
    gap: 10px;
    margin-top: 0px;
  }
`;

const Button = styled.button`
  font-weight: bold;
  outline: none;
  white-space: nowrap;
  font-size: 16px;
  border: 1px solid #d0d0d0;
  padding: 15px 50px;
  border-radius: 8px;
  background-color: white;
  &:hover {
    background-color: ${({ theme }) => theme?.Header?.color || "#f2f7cc"};
    color: white;
  }
  &:active {
    background-color: ${({ theme }) => theme?.Header?.color || "#f2f7cc"};
    color: white;
  }
  &.selected {
    background-color: ${({ theme }) =>
      theme?.Header?.color || "#f2f7cc"} !important;
    color: white !important;
  }
  &:disabled:hover {
    background-color: white;
    color: #b7b7d3;
  }
  @media only screen and (max-width: 768px) {
    width: 100%;
  }
`;

const SwitchButton = styled.div`
  margin-bottom: 5px;
  @media (max-width: 767px) {
    position: relative;
    right: 30px;
    bottom: -12px;
  }
`;

const Item = styled.div`
  display: flex;
  justify-content: center;
  align-items: center;
  gap: 10px;
`;
const Question = styled.label`
  font-weight: 500;
  width: 300px;
  text-align: left;
  margin-top: 10px;
`;
